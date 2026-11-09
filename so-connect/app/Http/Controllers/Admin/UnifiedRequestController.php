<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Request as ActionRequest;
use App\Services\ActionLogger;
use App\Services\UnifiedRequestDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnifiedRequestController extends Controller
{
    public function index(Request $request, UnifiedRequestDecisionService $decisions)
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_keys(UnifiedRequestDecisionService::workflows()))],
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'approved', 'rejected'])],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $workflows = UnifiedRequestDecisionService::workflows();
        $forms = $decisions->configuredForms();
        $formToWorkflow = collect($forms)->mapWithKeys(fn ($form, $key) => [(int) $form->getKey() => $key])->all();
        $formIds = array_keys($formToWorkflow);
        $type = $validated['type'] ?? null;
        $status = $validated['status'] ?? 'pending';
        $search = trim((string) ($validated['q'] ?? ''));

        $base = DB::table('requests as r')
            ->leftJoin('approvals as a', 'a.request', '=', 'r.request_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'r.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('r.action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->whereIn('r.form_id', $formIds);

        $pendingCounts = (clone $base)
            ->whereNull('a.approval_id')
            ->groupBy('r.form_id')
            ->selectRaw('r.form_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'r.form_id');

        foreach ($workflows as $key => &$workflow) {
            $form = $forms[$key] ?? null;
            $workflow['form_id'] = $form ? (int) $form->getKey() : null;
            $workflow['configured'] = $form !== null;
            $workflow['pending'] = $form ? (int) ($pendingCounts[(int) $form->getKey()] ?? 0) : 0;
        }
        unset($workflow);

        $query = clone $base;
        if ($type) {
            isset($forms[$type])
                ? $query->where('r.form_id', (int) $forms[$type]->getKey())
                : $query->whereRaw('1 = 0');
        }

        match ($status) {
            'pending' => $query->whereNull('a.approval_id'),
            'approved' => $query->whereNotNull('a.approval_id')->where('a.is_rejected', false),
            'rejected' => $query->whereNotNull('a.approval_id')->where('a.is_rejected', true),
            default => null,
        };

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('od.name', 'like', $like)
                    ->orWhere('p.first_name', 'like', $like)
                    ->orWhere('p.last_name', 'like', $like)
                    ->orWhere('u.user_email', 'like', $like);
                if (ctype_digit($search)) {
                    $query->orWhere('r.request_id', (int) $search);
                }
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('r.requested_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('r.requested_at', '<=', $validated['to']);
        }

        $requests = $query
            ->orderByDesc('r.requested_at')
            ->select([
                'r.request_id', 'r.form_id', 'r.requested_at', 'r.payload',
                'p.first_name', 'p.last_name', 'u.user_email', 'od.name as organization_name',
                'a.approval_id', 'a.approved_at', 'a.is_rejected', 'a.rejection_reason',
            ])
            ->paginate(20)
            ->withQueryString();

        $requests->through(function ($row) use ($formToWorkflow, $workflows) {
            $key = $formToWorkflow[(int) $row->form_id] ?? null;
            $row->workflow_key = $key;
            $row->workflow_label = $key ? $workflows[$key]['label'] : 'Request';
            $row->show_url = $key ? route($workflows[$key]['show_route'], $row->request_id) : '#';
            $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
            $row->requester_name = $name !== '' ? $name : ($row->user_email ?: 'Unknown requester');
            $row->organization_name = $row->organization_name ?: 'Unknown organization';
            $row->status = $row->approval_id === null ? 'pending' : ((bool) $row->is_rejected ? 'rejected' : 'approved');

            return $row;
        });

        return view('pages.admin.requests.index', [
            'title' => 'Requests & Approvals Center',
            'workflows' => $workflows,
            'requests' => $requests,
            'activeType' => $type,
            'activeStatus' => $status,
            'pendingTotal' => collect($workflows)->sum('pending'),
        ]);
    }

    public function bulk(Request $request, UnifiedRequestDecisionService $decisions): RedirectResponse
    {
        $validated = $request->validate([
            'request_ids' => ['required', 'array', 'min:1', 'max:25'],
            'request_ids.*' => ['required', 'integer', 'distinct'],
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'required_if:decision,reject', 'string', 'min:3', 'max:1000'],
        ]);

        $rejectionReason = isset($validated['rejection_reason'])
            ? trim($validated['rejection_reason'])
            : null;

        if ($validated['decision'] === 'reject' && $rejectionReason === '') {
            return back()->withErrors(['rejection_reason' => 'A rejection reason is required.']);
        }

        $items = ActionRequest::query()
            ->whereIn('request_id', $validated['request_ids'])
            ->get()
            ->keyBy('request_id');
        $succeeded = [];
        $failed = [];
        $warnings = [];
        $adminId = (int) $request->user()->getKey();

        foreach ($validated['request_ids'] as $requestId) {
            $item = $items->get((int) $requestId);
            if (! $item) {
                $failed[] = '#'.$requestId.': request was not found.';

                continue;
            }

            try {
                $result = $decisions->decide(
                    $item,
                    $adminId,
                    $validated['decision'],
                    $rejectionReason,
                );
                $succeeded[] = (int) $requestId;
                if ($result['warning']) {
                    $warnings[] = '#'.$requestId.': '.$result['warning'];
                }
            } catch (\Throwable $throwable) {
                $failed[] = '#'.$requestId.': '.$throwable->getMessage();
            }
        }

        if ($succeeded !== []) {
            ActionLogger::log(
                ActionLogger::CATEGORY_REQUESTS,
                'bulk_'.$validated['decision'],
                ucfirst($validated['decision']).'d '.count($succeeded).' request(s) from the unified center.',
                ['request_ids' => $succeeded, 'failed_count' => count($failed)],
            );
        }

        $verb = $validated['decision'] === 'approve' ? 'approved' : 'rejected';

        $response = back()
            ->with('bulk_failures', $failed)
            ->with('bulk_warnings', $warnings);

        return $succeeded === []
            ? $response
            : $response->with('success', count($succeeded).' request(s) '.$verb.'.');
    }

    public function decide(
        Request $request,
        int $requestId,
        UnifiedRequestDecisionService $decisions,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'required_if:decision,reject', 'string', 'min:3', 'max:1000'],
        ]);

        $rejectionReason = isset($validated['rejection_reason'])
            ? trim($validated['rejection_reason'])
            : null;

        if ($validated['decision'] === 'reject' && $rejectionReason === '') {
            return back()->withErrors(['rejection_reason' => 'A rejection reason is required.']);
        }

        $item = ActionRequest::query()->findOrFail($requestId);
        $workflow = $decisions->workflowFor($item);

        if ($workflow === null) {
            return back()->withErrors(['request' => 'This request is not part of the unified approvals center.']);
        }

        try {
            $result = $decisions->decide(
                $item,
                (int) $request->user()->getKey(),
                $validated['decision'],
                $rejectionReason,
            );
        } catch (\Throwable $throwable) {
            return back()->withErrors(['request' => $throwable->getMessage()]);
        }

        ActionLogger::log(
            ActionLogger::CATEGORY_REQUESTS,
            $validated['decision'].'_request',
            ucfirst($validated['decision']).'d request #'.$requestId.' from its review page.',
            ['request_id' => $requestId, 'workflow' => $workflow],
            $item,
        );

        $verb = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
        $message = UnifiedRequestDecisionService::workflows()[$workflow]['label'].' '.$verb.'.';
        if ($result['warning']) {
            $message .= ' '.$result['warning'];
        }

        return redirect()
            ->route(UnifiedRequestDecisionService::workflows()[$workflow]['index_route'])
            ->with('success', $message);
    }
}
