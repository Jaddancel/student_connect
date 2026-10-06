<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LoginLogsExport;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\Organization;
use App\Services\RecordQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        $logs = $this->recordQueryService->loginLogsQuery($filters)->paginate(25)->withQueryString();
        $logs->getCollection()->transform(fn (LoginLog $log) => $this->recordQueryService->mapLoginLog($log));

        $organizations = $this->organizationOptions();

        return view('pages.admin.audit-logs.index', [
            'logs' => $logs,
            'organizations' => $organizations,
            'filters' => $filters,
        ]);
    }

    public function exportJson(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        return response()->json($this->recordQueryService->getLoginLogs($filters))
            ->header('Content-Disposition', 'attachment; filename="audit-logs-export.json"');
    }

    public function exportPrint(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $logs = $this->recordQueryService->getLoginLogs($filters);

        return view('exports.login-logs-print', compact('logs'));
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return Excel::download(
            new LoginLogsExport($this->recordQueryService->getLoginLogs($filters)),
            'audit-logs-export.xlsx'
        );
    }

    private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'org_id' => $request->query('org_id'),
            'name' => $request->query('name'),
        ]);
    }

    private function organizationOptions()
    {
        return Organization::with('detail')
            ->orderBy('organization_id')
            ->get()
            ->map(fn (Organization $org) => [
                'organization_id' => $org->organization_id,
                'name' => $org->getRelation('detail')?->name ?? 'Unnamed Organization',
            ]);
    }
}
