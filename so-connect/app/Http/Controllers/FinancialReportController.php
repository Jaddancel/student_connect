<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        if ($isAdmin) {
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        } else {
            $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $presidentOrgIds)
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        }

        return view('pages.form.financial-report', [
            'title'         => 'Financial Report',
            'isAdmin'       => $isAdmin,
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $validated = $request->validate([
            'organization_id'      => ['required', 'integer', 'min:1'],
            'organization'         => ['required', 'string', 'max:255'],
            'school_year'          => ['required', 'string', 'max:20'],
            'fund_source'          => ['required', 'array', 'min:1'],
            'fund_source.*'        => ['required', 'string', 'max:255'],
            'fund_amount'          => ['required', 'array', 'min:1'],
            'fund_amount.*'        => ['required', 'numeric', 'min:0'],
            'total_funds'          => ['required', 'numeric', 'min:0'],
            'activity_title'       => ['nullable', 'array'],
            'activity_title.*'     => ['nullable', 'string', 'max:255'],
            'activity_date'        => ['nullable', 'array'],
            'activity_date.*'      => ['nullable', 'date'],
            'item'                 => ['nullable', 'array'],
            'item.*'               => ['nullable', 'string', 'max:255'],
            'amount_per_unit'      => ['nullable', 'array'],
            'amount_per_unit.*'    => ['nullable', 'numeric', 'min:0'],
            'quantity'             => ['nullable', 'array'],
            'quantity.*'           => ['nullable', 'numeric', 'min:0'],
            'total_expenses'       => ['required', 'numeric', 'min:0'],
            'cash_on_hand'         => ['required', 'numeric'],
            'name_of_treasurer'    => ['required', 'string', 'max:255'],
            'signature_treasurer'  => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_auditor'  => ['required', 'string', 'max:255'],
            'signature_auditor'    => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_president'=> ['required', 'string', 'max:255'],
            'signature_president'  => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_adviser'  => ['required', 'string', 'max:255'],
            'signature_adviser'    => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $presidentOrgIds, true)) {
                abort(403);
            }
        }

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $fundSources = $validated['fund_source'];
        $fundAmounts = $validated['fund_amount'];
        $fundRows = [];
        foreach ($fundSources as $i => $source) {
            $fundRows[] = [
                'fund_source' => $source,
                'amount'      => $fundAmounts[$i] ?? 0,
            ];
        }

        $activityTitles  = $validated['activity_title'] ?? [];
        $activityDates   = $validated['activity_date'] ?? [];
        $items           = $validated['item'] ?? [];
        $amountsPerUnit  = $validated['amount_per_unit'] ?? [];
        $quantities      = $validated['quantity'] ?? [];
        $expenseRows = [];
        foreach ($activityTitles as $i => $title) {
            $qty = (float) ($quantities[$i] ?? 0);
            $apu = (float) ($amountsPerUnit[$i] ?? 0);
            $expenseRows[] = [
                'activity_title'  => $title,
                'activity_date'   => $activityDates[$i] ?? '',
                'item'            => $items[$i] ?? '',
                'amount_per_unit' => $apu,
                'quantity'        => $qty,
                'price_total'     => $apu * $qty,
            ];
        }

        $payload = [
            'organization'          => $validated['organization'],
            'school_year'           => $validated['school_year'],
            'fund_rows'             => $fundRows,
            'total_funds'           => $validated['total_funds'],
            'expense_rows'          => $expenseRows,
            'total_expenses'        => $validated['total_expenses'],
            'cash_on_hand'          => $validated['cash_on_hand'],
            'name_of_treasurer'     => $validated['name_of_treasurer'],
            'name_of_the_auditor'   => $validated['name_of_the_auditor'],
            'name_of_the_president' => $validated['name_of_the_president'],
            'name_of_the_adviser'   => $validated['name_of_the_adviser'],
        ];

        foreach (['signature_treasurer', 'signature_auditor', 'signature_president', 'signature_adviser'] as $fileField) {
            if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                $file = $request->file($fileField);
                $path = $file->storeAs(
                    $sigDir,
                    Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
                $payload[$fileField] = $path;
            } else {
                $payload[$fileField] = '';
            }
        }

        $form = Form::query()->where('route_name', 'financial-report')->firstOrFail();

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        $template = Template::query()
            ->where('form_id', $form->id)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->with(['mappings.field'])
            ->first();

        if (! $template) {
            return redirect()->route('financial-report')
                ->with('status', 'Report submitted. No active template found — document not generated yet.');
        }

        try {
            $documentGenerationService->generateFromSubmission(
                $submission->fresh(['form']),
                $template,
                null,
                $userId,
            );

            return redirect()->route('download-files')
                ->with('success', 'Financial report generated and is now available for download.');
        } catch (\Throwable $e) {
            return redirect()->route('financial-report')
                ->with('status', 'Report submitted, but document generation failed: '.$e->getMessage());
        }
    }
}
