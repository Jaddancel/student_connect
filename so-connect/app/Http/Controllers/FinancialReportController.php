<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
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
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $officerOrgIds)
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        }

        $orgIds = $organizations->pluck('organization_id')->map(fn ($id) => (int) $id)->toArray();

        $eventRows = DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->whereIn('e.organization', $orgIds)
            ->select(['e.organization', 'ed.name', DB::raw('DATE(ed.start_time) as event_date')])
            ->orderBy('ed.start_time')
            ->get();

        $orgEvents = [];
        foreach ($eventRows as $row) {
            $orgEvents[(int) $row->organization][] = [
                'name' => $row->name,
                'date' => $row->event_date,
            ];
        }

        return view('pages.form.financial-report', [
            'title'             => 'Financial Report',
            'isAdmin'           => $isAdmin,
            'organizations'     => $organizations,
            'currentSchoolYear' => Semester::currentSchoolYear(),
            'orgEvents'         => $orgEvents,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        // Drop fully empty fund source rows so only filled rows are validated.
        $fundSources = $request->input('fundSource', []);
        $fundAmounts = $request->input('amount', []);
        $fundRows = collect($fundSources)->map(function ($source, $i) use ($fundAmounts) {
            $sourceValue = is_string($source) ? trim($source) : '';
            $amountValue = $fundAmounts[$i] ?? null;

            return [
                'source' => $sourceValue,
                'amount' => $amountValue,
            ];
        })->filter(function ($row) {
            $amountValue = $row['amount'];
            $hasSource = $row['source'] !== '';
            $hasAmount = ! ($amountValue === null || $amountValue === '' || $amountValue === 0 || $amountValue === '0');

            return $hasSource || $hasAmount;
        })->values();

        $request->merge([
            'fundSource' => $fundRows->pluck('source')->all(),
            'amount'     => $fundRows->pluck('amount')->all(),
        ]);

        $validated = $request->validate([
            'organization_id'       => ['required', 'integer', 'min:1'],
            'organization'          => ['required', 'string', 'max:255'],
            'date'                  => ['required', 'string', 'max:20'],
            'fundSource'            => ['required', 'array', 'min:1'],
            'fundSource.*'          => ['required', 'string', 'max:255'],
            'amount'                => ['required', 'array', 'min:1'],
            'amount.*'              => ['required', 'numeric', 'min:0'],
            'totalFunds'            => ['required', 'numeric', 'min:0'],
            'activityTitle'         => ['nullable', 'array'],
            'activityTitle.*'       => ['nullable', 'string', 'max:255'],
            'activityDate'          => ['nullable', 'array'],
            'activityDate.*'        => ['nullable', 'date'],
            'item'                  => ['nullable', 'array'],
            'item.*'                => ['nullable', 'string', 'max:255'],
            'amountPerUnit'         => ['nullable', 'array'],
            'amountPerUnit.*'       => ['nullable', 'numeric', 'min:0'],
            'quantity'              => ['nullable', 'array'],
            'quantity.*'            => ['nullable', 'numeric', 'min:0'],
            'totalExpenses'         => ['required', 'numeric', 'min:0'],
            'cashOnHand'            => ['required', 'numeric'],
            'name_of_treasurer'     => ['required', 'string', 'max:255'],
            'signature1'            => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_auditor'   => ['required', 'string', 'max:255'],
            'signature2'            => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_president' => ['required', 'string', 'max:255'],
            'signature3'            => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'name_of_the_adviser'   => ['required', 'string', 'max:255'],
            'signature4'            => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $officerOrgIds, true)) {
                abort(403);
            }
        }

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $fundSources = $validated['fundSource'];
        $fundAmounts = $validated['amount'];
        $fundRows = [];
        foreach ($fundSources as $i => $source) {
            $fundRows[] = [
                'fundSource' => $source,
                'amount'     => $fundAmounts[$i] ?? 0,
            ];
        }

        $activityTitles = $validated['activityTitle'] ?? [];
        $activityDates  = $validated['activityDate'] ?? [];
        $items          = $validated['item'] ?? [];
        $amountsPerUnit = $validated['amountPerUnit'] ?? [];
        $quantities     = $validated['quantity'] ?? [];
        $expenseRows = [];
        $priceTotals = [];
        foreach ($activityTitles as $i => $title) {
            $qty = (float) ($quantities[$i] ?? 0);
            $apu = (float) ($amountsPerUnit[$i] ?? 0);
            $priceTotals[$i] = $apu * $qty;
            $expenseRows[] = [
                'activityTitle' => $title,
                'activityDate'  => $activityDates[$i] ?? '',
                'item'          => $items[$i] ?? '',
                'amountPerUnit' => $apu,
                'quantity'      => $qty,
                'priceTotal'    => $priceTotals[$i],
            ];
        }

        $payload = [
            'organization'          => $validated['organization'],
            'date'                  => $validated['date'],
            'fundSource'            => $fundSources,
            'amount'                => $fundAmounts,
            'fundRows'              => $fundRows,
            'totalFunds'            => $validated['totalFunds'],
            'activityTitle'         => $activityTitles,
            'activityDate'          => $activityDates,
            'item'                  => $items,
            'amountPerUnit'         => $amountsPerUnit,
            'quantity'              => $quantities,
            'priceTotal'            => $priceTotals,
            'expenseRows'           => $expenseRows,
            'totalExpenses'         => $validated['totalExpenses'],
            'cashOnHand'            => $validated['cashOnHand'],
            'name_of_treasurer'     => $validated['name_of_treasurer'],
            'name_of_the_auditor'   => $validated['name_of_the_auditor'],
            'name_of_the_president' => $validated['name_of_the_president'],
            'name_of_the_adviser'   => $validated['name_of_the_adviser'],
        ];

        foreach (['signature1', 'signature2', 'signature3', 'signature4'] as $fileField) {
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
