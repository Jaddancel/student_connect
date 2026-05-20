<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\LoginLog;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
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

    public function adminIndex()
    {
        $organizations = Organization::with('detail')
            ->orderBy('organization_id')
            ->get()
            ->map(fn ($org) => [
                'organization_id' => $org->organization_id,
                'name'            => $org->getRelation('detail')?->name ?? 'Unnamed Organization',
            ]);

        return view('pages.sidebar.admin-export', compact('organizations'));
    }

    public function exportOrgDataJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);

        $data = $organizations->map(function (Organization $org) {
            $detail   = $org->getRelation('detail');
            $officers = $org->officersOfThisOrganization->map(fn ($o) => [
                'org_officer_id' => $o->org_officer_id,
                'user_id'        => $o->user,
                'name'           => optional($o->user)->name,
                'role'           => $o->role,
                'position'       => $o->position ?? null,
                'member_since'   => optional($o->member_since)->toDateString(),
            ]);

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
        $records = $this->getRequestRecords($orgId);

        $filename = $orgId ? "org-{$orgId}-request-records-export.json" : 'request-records-export.json';

        return response()->json($records)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportRequestRecordsPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->getRequestRecords($orgId);

        $orgName = null;
        if ($orgId) {
            $org = Organization::with('detail')->find($orgId);
            $orgName = $org?->getRelation('detail')?->name;
        }

        return view('exports.request-records-print', array_merge($records, compact('orgName')));
    }

    public function adminExportOrgDataJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);

        $data = $organizations->map(function (Organization $org) {
            $detail   = $org->getRelation('detail');
            $officers = $org->officersOfThisOrganization->map(fn ($o) => [
                'org_officer_id' => $o->org_officer_id,
                'user_id'        => $o->user,
                'name'           => optional($o->user)->name,
                'role'           => $o->role,
                'position'       => $o->position ?? null,
                'member_since'   => optional($o->member_since)->toDateString(),
            ]);

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

    public function adminExportOrgDataPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $organizations = $this->loadOrganizations($orgId);

        return view('exports.org-data-print', compact('organizations'));
    }

    public function adminExportRequestRecordsJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $records = $this->getRequestRecords($orgId, true);

        $filename = $orgId ? "org-{$orgId}-request-records-export.json" : 'request-records-export.json';

        return response()->json($records)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function adminExportRequestRecordsPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->getRequestRecords($orgId, true);

        $orgName = null;
        if ($orgId) {
            $org = Organization::with('detail')->find($orgId);
            $orgName = $org?->getRelation('detail')?->name;
        }

        return view('exports.request-records-print', array_merge($records, compact('orgName')));
    }

    public function exportLoginLogsJson(Request $request): JsonResponse
    {
        return response()->json($this->getLoginLogs())
            ->header('Content-Disposition', 'attachment; filename="login-activity-export.json"');
    }

    public function exportLoginLogsPrint(Request $request)
    {
        $logs = $this->getLoginLogs();

        return view('exports.login-logs-print', compact('logs'));
    }

    private function getLoginLogs(): array
    {
        return LoginLog::with(['user.profile'])
            ->orderByDesc('logged_at')
            ->get()
            ->map(function (LoginLog $log) {
                $profile = $log->user?->profile;
                $name = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

                return [
                    'user_email'  => $log->user?->user_email ?? '-',
                    'name'        => $name !== '' ? $name : '-',
                    'interaction' => $log->interaction,
                    'logged_at'   => optional($log->logged_at)->toDateTimeString(),
                ];
            })
            ->all();
    }

    private function loadOrganizations(?string $orgId)
    {
        $query = Organization::with(['detail', 'officersOfThisOrganization.user']);

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        return $query->get()->each(function (Organization $org) {
            $detail = $org->getRelation('detail');
            $org->org_name     = $detail?->name ?? 'Unnamed Organization';
            $org->org_initials = $detail?->initials;
        });
    }

    private function getRequestRecords(?string $orgId, bool $excludeAdminUsers = false): array
    {
        $query = ActionRequest::with(['requestType', 'requester'])->orderByDesc('requested_at');

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        if ($excludeAdminUsers) {
            $query->whereHas('requester', fn ($q) => $q->whereNotIn('user_type', [1, 2]));
        }

        $allRequests = $query->get();

        $approvedIds = Approval::where('is_rejected', false)->pluck('request')->flip();
        $rejectedIds = Approval::where('is_rejected', true)->pluck('request')->flip();

        $accepted = [];
        $pending  = [];
        $rejected = [];

        foreach ($allRequests as $req) {
            $row = [
                'user_id'      => $req->user,
                'org_id'       => $req->organization_id,
                'request_time' => optional($req->requested_at)->toDateTimeString(),
                'request_type' => optional($req->requestType)->name ?? 'Unknown',
            ];

            if (isset($rejectedIds[$req->request_id])) {
                $rejected[] = $row;
            } elseif (isset($approvedIds[$req->request_id])) {
                $accepted[] = $row;
            } else {
                $pending[] = $row;
            }
        }

        return compact('accepted', 'pending', 'rejected');
    }
}
