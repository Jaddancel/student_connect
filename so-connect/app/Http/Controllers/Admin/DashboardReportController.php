<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DashboardReportExport;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Services\RecordQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DashboardReportController extends Controller
{
    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index(Request $request)
    {
        $this->recordActivity('DASHBOARD_REPORT_VIEW');

        return view('pages.admin.dashboard-reports.index', [
            'report' => $this->buildReport(),
            'generatedAt' => now()->toDateTimeString(),
            'recentAuditLogs' => $this->recentAuditLogRows(),
        ]);
    }

    public function recentAuditLogs(Request $request)
    {
        return response()->json($this->recentAuditLogRows());
    }

    public function exportCsv(Request $request)
    {
        $this->recordActivity('DASHBOARD_REPORT_EXPORT_CSV');

        $rows = $this->buildReportRows();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Metric', 'Value']);

            foreach ($rows as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }

            fclose($handle);
        }, 'admin-dashboard-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->recordActivity('DASHBOARD_REPORT_EXPORT_EXCEL');

        return Excel::download(
            new DashboardReportExport($this->buildReportRows()),
            'admin-dashboard-report.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $this->recordActivity('DASHBOARD_REPORT_EXPORT_PDF');

        $report = $this->buildReport();

        $pdf = Pdf::loadView('exports.dashboard-report-pdf', [
            'report' => $report,
            'generatedAt' => now()->toDateTimeString(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('admin-dashboard-report.pdf');
    }

    private function buildReport(): array
    {
        $totalUsers = DB::table('users')->where('user_type', '>', 1)->count();
        $totalOrganizations = DB::table('organizations')->count();
        $pendingProfileRequests = DB::table('requests as r')
            ->where('r.action_type', 9)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('approvals as a')
                ->whereColumn('a.request', 'r.request_id'))
            ->count();
        $pendingOfficerRequests = DB::table('requests as r')
            ->where('r.action_type', 12)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('approvals as a')
                ->whereColumn('a.request', 'r.request_id'))
            ->count();

        $workplanStats = DB::table('workplans')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentActivity = DB::table('requests as r')
            ->leftJoin('approvals as a', 'a.request', '=', 'r.request_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'r.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->orderByDesc('r.requested_at')
            ->limit(10)
            ->get([
                'r.request_id',
                'r.action_type',
                'r.requested_at',
                DB::raw("COALESCE(p.first_name, '') as first_name"),
                DB::raw("COALESCE(p.last_name, '') as last_name"),
                DB::raw("COALESCE(u.user_email, 'Unknown') as user_email"),
                DB::raw("COALESCE(od.name, '') as organization_name"),
                'a.is_rejected',
                'a.approved_at',
            ])
            ->map(function ($row): object {
                $labels = [
                    1 => 'Activity Request',
                    2 => 'Accomplishment Report',
                    3 => 'Financial Report',
                    4 => 'Org Recognition',
                    5 => 'Joint Statement',
                    6 => 'Project Request',
                    9 => 'Profile Match',
                    11 => 'Event Plan',
                    12 => 'Officer Account',
                ];

                $row->action_label = $labels[(int) $row->action_type] ?? 'Request #' . $row->action_type;
                $row->status = is_null($row->is_rejected) ? 'pending' : ($row->is_rejected ? 'rejected' : 'approved');
                $row->requester_name = trim($row->first_name . ' ' . $row->last_name) ?: $row->user_email;

                return $row;
            });

        return [
            'totals' => [
                'users' => $totalUsers,
                'organizations' => $totalOrganizations,
                'pending_profile_requests' => $pendingProfileRequests,
                'pending_officer_requests' => $pendingOfficerRequests,
                'workplans_active' => (int) ($workplanStats['active'] ?? 0),
                'workplans_finalized' => (int) ($workplanStats['finalized'] ?? 0),
                'workplans_archived' => (int) ($workplanStats['archived'] ?? 0),
            ],
            'recent_activity' => $recentActivity,
        ];
    }

    private function buildReportRows(): array
    {
        $report = $this->buildReport();
        $totals = $report['totals'];

        return [
            ['label' => 'Registered Users', 'value' => $totals['users']],
            ['label' => 'Organizations', 'value' => $totals['organizations']],
            ['label' => 'Pending Profile Requests', 'value' => $totals['pending_profile_requests']],
            ['label' => 'Pending Officer Requests', 'value' => $totals['pending_officer_requests']],
            ['label' => 'Active Workplans', 'value' => $totals['workplans_active']],
            ['label' => 'Finalized Workplans', 'value' => $totals['workplans_finalized']],
            ['label' => 'Archived Workplans', 'value' => $totals['workplans_archived']],
        ];
    }

    private function recentAuditLogRows(): array
    {
        return LoginLog::query()
            ->with(['user.profile'])
            ->orderByDesc('logged_at')
            ->limit(8)
            ->get()
            ->map(fn (LoginLog $log) => $this->recordQueryService->mapLoginLog($log))
            ->all();
    }

    private function recordActivity(string $interaction): void
    {
        LoginLog::create([
            'user_id' => auth()->id(),
            'interaction' => $interaction,
            'logged_at' => now(),
        ]);
    }
}
