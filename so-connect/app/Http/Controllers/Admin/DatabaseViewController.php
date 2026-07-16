<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrganizationOfficersExport;
use App\Exports\OrganizationsExport;
use App\Http\Controllers\Admin\Concerns\PaginatesArrays;
use App\Http\Controllers\Controller;
use App\Services\RecordQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DatabaseViewController extends Controller
{
    use PaginatesArrays;

    public function __construct(private RecordQueryService $recordQueryService) {}

    /**
     * Page 1 — Organizations. Optionally filtered to a single organization.
     */
    public function index(Request $request)
    {
        $orgId = $this->requestedOrgId($request);
        $organizations = $this->recordQueryService->getOrganizations();

        return view('pages.admin.database-view.index', [
            'organizations' => $this->paginateArray($this->filterOrganizations($organizations, $orgId), 15),
            'allOrganizations' => $organizations,
            'selectedOrgId' => $orgId,
        ]);
    }

    /**
     * Page 2 — Officers per organization. Optionally filtered to a single organization.
     */
    public function officers(Request $request)
    {
        $orgId = $this->requestedOrgId($request);
        $organizations = $this->recordQueryService->getOrganizations();

        // Each organization's roster paginates independently via its own page
        // parameter, on top of the outer pagination over the organizations.
        $officersByOrg = collect($this->recordQueryService->getOrganizationOfficers())
            ->map(fn ($officers, $orgId) => $this->paginateArray($officers, 10, 'org_'.$orgId.'_page'))
            ->all();

        return view('pages.admin.database-view.officers', [
            'organizations' => $this->paginateArray($this->filterOrganizations($organizations, $orgId), 10),
            'allOrganizations' => $organizations,
            'officersByOrg' => $officersByOrg,
            'selectedOrgId' => $orgId,
        ]);
    }

    public function exportOrgsJson(): JsonResponse
    {
        return response()->json(['organizations' => $this->recordQueryService->getOrganizations()])
            ->header('Content-Disposition', 'attachment; filename="organizations-export.json"');
    }

    public function exportOrgsPrint()
    {
        return view('exports.organizations-print', [
            'organizations' => $this->recordQueryService->getOrganizations(),
        ]);
    }

    public function exportOrgsXlsx()
    {
        return Excel::download(
            new OrganizationsExport($this->recordQueryService->getOrganizations()),
            'organizations-export.xlsx'
        );
    }

    public function exportOfficersJson(Request $request): JsonResponse
    {
        return response()->json(['officers' => $this->flattenOfficers($this->requestedOrgId($request))])
            ->header('Content-Disposition', 'attachment; filename="organization-officers-export.json"');
    }

    public function exportOfficersPrint(Request $request)
    {
        return view('exports.organization-officers-print', [
            'officers' => $this->flattenOfficers($this->requestedOrgId($request)),
        ]);
    }

    public function exportOfficersXlsx(Request $request)
    {
        return Excel::download(
            new OrganizationOfficersExport($this->flattenOfficers($this->requestedOrgId($request))),
            'organization-officers-export.xlsx'
        );
    }

    /**
     * Read the requested organization id, treating blank/missing as "all".
     */
    private function requestedOrgId(Request $request): ?int
    {
        $orgId = $request->query('organization_id');

        return ($orgId === null || $orgId === '') ? null : (int) $orgId;
    }

    /**
     * Restrict the organization list to a single org when one is selected.
     *
     * @param  array<int, array<string, mixed>>  $organizations
     * @return array<int, array<string, mixed>>
     */
    private function filterOrganizations(array $organizations, ?int $orgId): array
    {
        if ($orgId === null) {
            return $organizations;
        }

        return array_values(array_filter(
            $organizations,
            fn ($org) => (int) $org['organization_id'] === $orgId
        ));
    }

    /**
     * Flatten the per-org officer roster into rows carrying the organization name.
     *
     * @return array<int, array<string, string>>
     */
    private function flattenOfficers(?int $orgId = null): array
    {
        $names = collect($this->recordQueryService->getOrganizations())
            ->pluck('name', 'organization_id');

        $rows = [];
        foreach ($this->recordQueryService->getOrganizationOfficers() as $groupOrgId => $officers) {
            if ($orgId !== null && (int) $groupOrgId !== $orgId) {
                continue;
            }

            $orgName = $names[$groupOrgId] ?? 'Unnamed Organization';
            foreach ($officers as $officer) {
                $rows[] = [
                    'organization' => $orgName,
                    'name' => $officer['name'],
                    'email' => $officer['email'],
                    'role' => $officer['role'],
                    'position' => $officer['position'],
                    'member_since' => $officer['member_since'],
                ];
            }
        }

        return $rows;
    }
}
