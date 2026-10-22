<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ActionLogsExport;
use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActionLogger;
use App\Services\RecordQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Admin Audit Logs: the superadmin action log ({@see ActionLogController})
 * scoped to officer accounts (user_type 3) and to the categories in SCOPE.
 * Searchable by user, category, organization and date range; exportable as
 * PDF, Excel and JSON.
 */
class AuditLogController extends Controller
{
    private const EXPORT_TITLE = 'Officer Action Logs';

    /** Category => allowed actions (null = all actions in the category). */
    private const SCOPE = [
        ActionLogger::CATEGORY_AUTH => ['login', 'logout'],
        ActionLogger::CATEGORY_REQUESTS => ['request_sent', 'request_accepted'],
        ActionLogger::CATEGORY_DOCUMENT_GENERATION => ['document_download', 'partial_form_generated', 'partial_form_uploaded'],
    ];

    public function __construct(private RecordQueryService $recordQueryService) {}

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        $logs = $this->recordQueryService
            ->actionLogsQuery($filters, User::TYPE_OFFICER, self::SCOPE)
            ->paginate(25)
            ->withQueryString();
        $logs->getCollection()->transform(fn (ActionLog $log) => $this->recordQueryService->mapActionLog($log));

        return view('pages.admin.audit-logs.index', [
            'title' => 'Audit Logs',
            'logs' => $logs,
            'filters' => $filters,
            'categories' => $this->categories(),
            'organizations' => $this->organizationOptions(),
        ]);
    }

    /**
     * Officer lookup for the "Filter by officer" modal (name or email).
     */
    public function searchOfficers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $term = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $officers = User::query()
            ->where('user_type', User::TYPE_OFFICER)
            ->with('profileRecord')
            ->where(function ($query) use ($term) {
                $query->where('user_email', 'like', "%{$term}%")
                    ->orWhereHas('profileRecord', function ($profileQuery) use ($term) {
                        $profileQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('middle_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            })
            ->orderBy('user_email')
            ->limit(20)
            ->get()
            ->map(function (User $user) {
                $profile = $user->getRelation('profileRecord');

                return [
                    'profile_id' => $profile?->profile_id,
                    'first_name' => $profile?->first_name,
                    'middle_name' => $profile?->middle_name,
                    'last_name' => $profile?->last_name,
                    'user_email' => $user->user_email,
                ];
            })
            ->values();

        return response()->json(['data' => $officers]);
    }

    public function exportJson(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        return response()->json($this->recordQueryService->getActionLogs($filters, User::TYPE_OFFICER, self::SCOPE))
            ->header('Content-Disposition', 'attachment; filename="audit-logs-export.json"');
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $logs = $this->recordQueryService->getActionLogs($filters, User::TYPE_OFFICER, self::SCOPE);

        $html = view('exports.action-logs-print', [
            'title' => self::EXPORT_TITLE,
            'logs' => $logs,
            'filters' => $filters,
            'categories' => $this->categories(),
            'organizationName' => isset($filters['org_id'])
                ? $this->organizationOptions()->firstWhere('organization_id', $filters['org_id'])['name'] ?? null
                : null,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download('audit-logs-export.pdf');
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return Excel::download(
            new ActionLogsExport(
                $this->recordQueryService->getActionLogs($filters, User::TYPE_OFFICER, self::SCOPE),
                'Officer Actions'
            ),
            'audit-logs-export.xlsx'
        );
    }

    /** @return array<string,string> */
    private function categories(): array
    {
        return array_intersect_key(ActionLogger::categories(), self::SCOPE);
    }

    private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'category' => $request->query('category'),
            'org_id' => $request->query('org_id'),
            'user' => $request->query('user'),
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
