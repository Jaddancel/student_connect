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
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $officerOrgIds)
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
            'schoolYear'           => ['required', 'string', 'max:20'],
            'fundSource'           => ['required', 'array', 'min:1'],
            'fundSource.*'         => ['required', 'string', 'max:255'],
            'fundAmount'           => ['required', 'array', 'min:1'],
            'fundAmount.*'         => ['required', 'numeric', 'min:0'],
            'totalFunds'           => ['required', 'numeric', 'min:0'],
            'activityTitle'        => ['nullable', 'array'],
            'activityTitle.*'      => ['nullable', 'string', 'max:255'],
            'activityDate'         => ['nullable', 'array'],
            'activityDate.*'       => ['nullable', 'date'],
            'item'                 => ['nullable', 'array'],
            'item.*'               => ['nullable', 'string', 'max:255'],
            'amountPerUnit'        => ['nullable', 'array'],
            'amountPerUnit.*'      => ['nullable', 'numeric', 'min:0'],
            'quantity'             => ['nullable', 'array'],
            'quantity.*'           => ['nullable', 'numeric', 'min:0'],
            'totalExpenses'        => ['required', 'numeric', 'min:0'],
            'cashOnHand'           => ['required', 'numeric'],
            'nameOfTreasurer'      => ['required', 'string', 'max:255'],
            'signatureTreasurer'   => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'nameOfTheAuditor'     => ['required', 'string', 'max:255'],
            'signatureAuditor'     => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'nameOfThePresident'   => ['required', 'string', 'max:255'],
            'signaturePresident'   => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'nameOfTheAdviser'     => ['required', 'string', 'max:255'],
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
        $fundAmounts = $validated['fundAmount'];
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
        foreach ($activityTitles as $i => $title) {
            $qty = (float) ($quantities[$i] ?? 0);
            $apu = (float) ($amountsPerUnit[$i] ?? 0);
            $expenseRows[] = [
                'activityTitle' => $title,
                'activityDate'  => $activityDates[$i] ?? '',
                'item'          => $items[$i] ?? '',
                'amountPerUnit' => $apu,
                'quantity'      => $qty,
                'priceTotal'    => $apu * $qty,
            ];
        }

        $payload = [
            'organization'      => $validated['organization'],
            'schoolYear'        => $validated['schoolYear'],
            'fundRows'          => $fundRows,
            'totalFunds'        => $validated['totalFunds'],
            'expenseRows'       => $expenseRows,
            'totalExpenses'     => $validated['totalExpenses'],
            'cashOnHand'        => $validated['cashOnHand'],
            'nameOfTreasurer'   => $validated['nameOfTreasurer'],
            'nameOfTheAuditor'  => $validated['nameOfTheAuditor'],
            'nameOfThePresident'=> $validated['nameOfThePresident'],
            'nameOfTheAdviser'  => $validated['nameOfTheAdviser'],
        ];

        foreach (['signatureTreasurer', 'signatureAuditor', 'signaturePresident'] as $fileField) {
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
