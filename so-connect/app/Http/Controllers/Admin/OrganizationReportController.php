<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Services\RecordQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Generates downloadable PDF reports for administrators:
 *  - a roster of every registered organization, and
 *  - the list of officers of each organization.
 */
class OrganizationReportController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    /**
     * Landing page listing the available reports.
     */
    public function index()
    {
        return view('pages.admin.reports.index', [
            'title' => 'Reports',
            'organizations' => $this->recordQueryService->getOrganizations(),
        ]);
    }

    /**
     * PDF report of all registered organizations.
     */
    public function organizations()
    {
        $organizations = $this->organizationRows();

        $pdf = Pdf::loadView('reports.organizations', [
            'organizations' => $organizations,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('registered-organizations-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * PDF report of each organization's officers. Optionally filtered to a
     * single organization via ?organization_id=.
     */
    public function officers(Request $request)
    {
        $orgId = $request->query('organization_id');
        $orgId = ($orgId === null || $orgId === '') ? null : (int) $orgId;

        $names = collect($this->recordQueryService->getOrganizations())
            ->keyBy('organization_id');

        $officersByOrg = $this->recordQueryService->getOrganizationOfficers();

        $sections = [];
        foreach ($names as $organizationId => $org) {
            if ($orgId !== null && (int) $organizationId !== $orgId) {
                continue;
            }

            $sections[] = [
                'name' => $org['name'],
                'initials' => $org['initials'],
                'officers' => $officersByOrg[$organizationId] ?? [],
            ];
        }

        $pdf = Pdf::loadView('reports.officers', [
            'sections' => $sections,
            'generatedAt' => now(),
            'filtered' => $orgId !== null,
        ])->setPaper('a4', 'portrait');

        $suffix = $orgId !== null ? 'organization-'.$orgId : 'all';

        return $pdf->download('organization-officers-'.$suffix.'-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Registered organizations with their type label and officer count.
     *
     * @return array<int, array<string, mixed>>
     */
    private function organizationRows(): array
    {
        $counts = DB::table('organization_officers')
            ->whereIn('role', ['officer', 'president'])
            ->select('organization', DB::raw('COUNT(*) as officer_count'))
            ->groupBy('organization')
            ->pluck('officer_count', 'organization');

        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->orderBy('o.organization_id')
            ->select(
                'o.organization_id',
                'o.organization_type',
                DB::raw("COALESCE(od.name, 'Unnamed Organization') as name"),
                'od.initials',
            )
            ->get()
            ->map(fn ($org) => [
                'organization_id' => (int) $org->organization_id,
                'name' => $org->name,
                'initials' => $org->initials,
                'type' => OrganizationType::label((int) $org->organization_type),
                'officer_count' => (int) ($counts[$org->organization_id] ?? 0),
            ])
            ->all();
    }
}
