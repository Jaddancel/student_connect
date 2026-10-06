<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RequestRecordsExport;
use App\Http\Controllers\Admin\Concerns\PaginatesArrays;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\RecordQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RequestRecordController extends Controller
{
    use PaginatesArrays;

    public function __construct(private RecordQueryService $recordQueryService) {}

    /**
     * Columns the request table can be sorted by.
     */
    private const SORTABLE = ['user_id', 'org_id', 'request_time', 'request_type', 'status'];

    public function index(Request $request)
    {
        $orgId = $request->query('org_id');
        $grouped = $this->recordQueryService->getRequestRecords($orgId, true);

        // Flatten the status buckets into a single list, tagging each row with
        // its status so the table can show and sort by it as a column.
        $rows = collect(['accepted', 'pending', 'rejected'])
            ->flatMap(fn ($status) => collect($grouped[$status])
                ->map(fn ($row) => $row + ['status' => $status]));

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'request_time';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $rows = $rows
            ->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc')
            ->values();

        return view('pages.admin.request-records.index', [
            'records' => $this->paginateArray($rows, 15),
            'organizations' => $this->organizationOptions(),
            'orgId' => $orgId,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function exportJson(Request $request): JsonResponse
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId, true);

        $filename = $orgId ? "org-{$orgId}-request-records-export.json" : 'request-records-export.json';

        return response()->json($records)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportPrint(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId, true);

        $orgName = null;
        if ($orgId) {
            $org = Organization::with('detail')->find($orgId);
            $orgName = $org?->getRelation('detail')?->name;
        }

        return view('exports.request-records-print', array_merge($records, compact('orgName')));
    }

    public function exportXlsx(Request $request)
    {
        $orgId = $request->query('org_id');
        $records = $this->recordQueryService->getRequestRecords($orgId, true);

        $filename = $orgId ? "org-{$orgId}-request-records-export.xlsx" : 'request-records-export.xlsx';

        return Excel::download(
            new RequestRecordsExport($records['accepted'], $records['pending'], $records['rejected']),
            $filename
        );
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
