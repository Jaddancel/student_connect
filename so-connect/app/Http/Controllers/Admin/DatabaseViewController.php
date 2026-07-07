<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrganizationOfficersExport;
use App\Exports\OrganizationsExport;
use App\Http\Controllers\Controller;
use App\Services\RecordQueryService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class DatabaseViewController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index()
    {
        $organizations = $this->recordQueryService->getOrganizations();
        $officersByOrg = $this->recordQueryService->getOrganizationOfficers();

        return view('pages.admin.database-view.index', [
            'organizations' => $organizations,
            'officersByOrg' => $officersByOrg,
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

    public function exportOfficersJson(): JsonResponse
    {
        return response()->json(['officers' => $this->flattenOfficers()])
            ->header('Content-Disposition', 'attachment; filename="organization-officers-export.json"');
    }

    public function exportOfficersPrint()
    {
        return view('exports.organization-officers-print', [
            'officers' => $this->flattenOfficers(),
        ]);
    }

    public function exportOfficersXlsx()
    {
        return Excel::download(
            new OrganizationOfficersExport($this->flattenOfficers()),
            'organization-officers-export.xlsx'
        );
    }

    /**
     * Flatten the per-org officer roster into rows carrying the organization name.
     *
     * @return array<int, array<string, string>>
     */
    private function flattenOfficers(): array
    {
        $names = collect($this->recordQueryService->getOrganizations())
            ->pluck('name', 'organization_id');

        $rows = [];
        foreach ($this->recordQueryService->getOrganizationOfficers() as $orgId => $officers) {
            $orgName = $names[$orgId] ?? 'Unnamed Organization';
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
