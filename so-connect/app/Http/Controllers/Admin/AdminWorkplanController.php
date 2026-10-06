<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workplan;
use App\Services\WorkplanService;
use Illuminate\Support\Facades\DB;

class AdminWorkplanController extends Controller
{
    public function index(WorkplanService $workplanService)
    {
        $workplans = Workplan::query()
            ->with(['semester'])
            ->orderByDesc('created_at')
            ->get();

        $orgIds = $workplans->pluck('organization_id')->unique()->filter()->values()->all();
        $orgNames = [];
        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()->pluck('name', 'organization_id')->all();
        }

        $finalizerIds = $workplans->pluck('finalized_by')->filter()->unique()->values()->all();
        $finalizerNames = [];
        if (! empty($finalizerIds)) {
            $finalizerNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $finalizerIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()->pluck('name', 'user_id')->all();
        }

        $rows = $workplans->map(function (Workplan $wp) use ($workplanService, $orgNames, $finalizerNames) {
            return [
                'workplan' => $wp,
                'semester' => $wp->semester,
                'org_name' => $orgNames[$wp->organization_id] ?? 'Unknown Organization',
                'plans_count' => $workplanService->getApprovedPlansForWorkplan($wp)->count(),
                'finalizer_name' => $wp->finalized_by ? ($finalizerNames[$wp->finalized_by] ?? 'Unknown') : null,
            ];
        });

        $grouped = $rows->groupBy(fn ($r) => $r['semester']?->semester_id ?? 0)
            ->map(function ($items) {
                return [
                    'semester' => $items->first()['semester'],
                    'workplans' => $items->values(),
                ];
            })
            ->sortByDesc(fn ($g) => $g['semester']?->starts_at)
            ->values();

        return view('pages.admin.workplans.index', [
            'title' => 'Workplans',
            'grouped' => $grouped,
        ]);
    }
}
