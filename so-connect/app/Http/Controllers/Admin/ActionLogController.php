<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ActionLogsExport;
use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Services\ActionLogger;
use App\Services\RecordQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Superadmin viewer for the administrator action log: searchable by user,
 * category and date range, exportable as PDF (dompdf), Excel and JSON.
 * Mirrors {@see AuditLogController}, which stays dedicated to login activity.
 */
class ActionLogController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        $logs = $this->recordQueryService->actionLogsQuery($filters)->paginate(25)->withQueryString();
        $logs->getCollection()->transform(fn (ActionLog $log) => $this->recordQueryService->mapActionLog($log));

        return view('pages.superadmin.action-logs.index', [
            'title' => 'Action Logs',
            'logs' => $logs,
            'filters' => $filters,
            'categories' => ActionLogger::categories(),
        ]);
    }

    public function exportJson(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        return response()->json($this->recordQueryService->getActionLogs($filters))
            ->header('Content-Disposition', 'attachment; filename="action-logs-export.json"');
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $logs = $this->recordQueryService->getActionLogs($filters);

        $html = view('exports.action-logs-print', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => ActionLogger::categories(),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download('action-logs-export.pdf');
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return Excel::download(
            new ActionLogsExport($this->recordQueryService->getActionLogs($filters)),
            'action-logs-export.xlsx'
        );
    }

    private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'category' => $request->query('category'),
            'user' => $request->query('user'),
        ]);
    }
}
