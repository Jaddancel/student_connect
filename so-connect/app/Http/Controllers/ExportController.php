<?php

namespace App\Http\Controllers;

use App\Exports\LoginLogsExport;
use App\Exports\OrgDataExport;
use App\Exports\RequestRecordsExport;
use App\Models\Organization;
use App\Services\RecordQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index()
    {
        $organizations = Organization::with('detail')
            ->orderBy('organization_id')
            ->get()
            ->map(fn ($org) => [
                'organization_id' => $org->organization_id,
                'name'            => $org->getRelation('detail')?->name ?? 'Unnamed Organization',
            ]);

        return view('pages.sidebar.superadmin-export', compact('organizations'));
    }

    public function exportOrgDataJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);

        $data = $organizations->map(function (Organization $org) {
            $detail   = $org->getRelation('detail');
            $officers = $org->officersOfThisOrganization->map(function ($o) {
                $userModel = $o->getRelations()['user'] ?? null;
                $profile   = $userModel?->getRelations()['profile'] ?? null;
                $nameParts = array_filter([
                    $profile?->first_name,
                    $profile?->middle_name,
                    $profile?->last_name,
                ], fn ($part) => trim((string) $part) !== '');
                $name = $nameParts ? trim(implode(' ', $nameParts)) : null;
                $position = $o->position ?: ($profile?->position ?? null);
                $position = is_string($position) ? trim($position) : $position;
                $position = $position !== '' ? $position : null;

                return [
                    'org_officer_id' => $o->org_officer_id,
                    'user_id'        => $o->getAttributes()['user'] ?? null,
                    'name'           => $name,
                    'role'           => $o->role,
                    'position'       => $o->position ?? null,
                    'member_since'   => optional($o->member_since)->toDateString(),
                ];
            });

            return [
                'organization_id'   => $org->organization_id,
                'name'              => $detail?->name,
                'initials'          => $detail?->initials,
                'organization_type' => $org->organization_type,
                'officers'          => $officers,
            ];
        });

        $filename = $orgId ? "org-{$orgId}-data-export.json" : 'org-data-export.json';

        return response()->json(['organizations' => $data])
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportOrgDataPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);

        return view('exports.org-data-print', compact('organizations'));
    }

    public function exportRequestRecordsJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId);

        $filename = $orgId ? "org-{$orgId}-request-records-export.json" : 'request-records-export.json';

        return response()->json($records)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportRequestRecordsPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId);

        $orgName = null;
        if ($orgId) {
            $org = Organization::with('detail')->find($orgId);
            $orgName = $org?->getRelation('detail')?->name;
        }

        return view('exports.request-records-print', array_merge($records, compact('orgName')));
    }

    public function exportOrgDataXlsx(Request $request)
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);
        $data = $this->buildOrgDataArray($organizations);

        $filename = $orgId ? "org-{$orgId}-data-export.xlsx" : 'org-data-export.xlsx';

        return Excel::download(new OrgDataExport(collect($data)), $filename);
    }

    public function exportRequestRecordsXlsx(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId);

        $filename = $orgId ? "org-{$orgId}-request-records-export.xlsx" : 'request-records-export.xlsx';

        return Excel::download(
            new RequestRecordsExport($records['accepted'], $records['pending'], $records['rejected']),
            $filename
        );
    }

    public function exportLoginLogsXlsx(Request $request)
    {
        return Excel::download(new LoginLogsExport($this->recordQueryService->getLoginLogs()), 'login-activity-export.xlsx');
    }

    public function exportLoginLogsJson(Request $request): JsonResponse
    {
        return response()->json($this->recordQueryService->getLoginLogs())
            ->header('Content-Disposition', 'attachment; filename="login-activity-export.json"');
    }

    public function exportLoginLogsPrint(Request $request)
    {
        $logs = $this->recordQueryService->getLoginLogs();

        return view('exports.login-logs-print', compact('logs'));
    }

    private function buildOrgDataArray($organizations): array
    {
        return $organizations->map(function (Organization $org) {
            $detail   = $org->getRelation('detail');
            $officers = $org->officersOfThisOrganization->map(function ($o) {
                $userModel = $o->getRelations()['user'] ?? null;
                $profile   = $userModel?->getRelations()['profile'] ?? null;
                $name      = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: null;

                return [
                    'org_officer_id' => $o->org_officer_id,
                    'user_id'        => $o->getAttributes()['user'] ?? null,
                    'name'           => $name,
                    'role'           => $o->role,
                    'position'       => $o->position ?? null,
                    'member_since'   => optional($o->member_since)->toDateString(),
                ];
            })->all();

            return [
                'organization_id'   => $org->organization_id,
                'name'              => $detail?->name,
                'initials'          => $detail?->initials,
                'organization_type' => $org->organization_type,
                'officers'          => $officers,
            ];
        })->all();
    }

    private function loadOrganizations(?string $orgId)
    {
        $query = Organization::with(['detail', 'officersOfThisOrganization.user.profile']);

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        return $query->get()->each(function (Organization $org) {
            $detail = $org->getRelation('detail');
            $org->org_name     = $detail?->name ?? 'Unnamed Organization';
            $org->org_initials = $detail?->initials;
        });
    }
}
