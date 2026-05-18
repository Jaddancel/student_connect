<?php

namespace App\Services;

use App\Models\EventPlan;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkplanService
{
    /**
     * Returns the semester currently in its active (vacation) window, or null.
     */
    public function getActiveSemester(): ?Semester
    {
        return Semester::currentlyActive();
    }

    /**
     * Find-or-create a workplan for a given org + semester combination.
     */
    public function resolveWorkplan(int $orgId, int $semesterId): Workplan
    {
        return Workplan::query()->firstOrCreate(
            ['organization_id' => $orgId, 'semester_id' => $semesterId],
            ['status' => 'active']
        );
    }

    /**
     * Returns approved EventPlans for the workplan's org whose target_date
     * falls within the semester's date range.
     */
    public function getApprovedPlansForWorkplan(Workplan $workplan): Collection
    {
        $workplan->loadMissing('semester');
        $semester = $workplan->semester;

        $query = EventPlan::query()
            ->where('organization_id', $workplan->organization_id)
            ->where('status', 'approved')
            ->where('target_date', '>=', $semester->starts_at);

        $endsAt = $semester->endsAt();
        if ($endsAt !== null) {
            $query->where('target_date', '<=', $endsAt);
        }

        return $query->orderBy('target_date')->get();
    }

    /**
     * Archive workplans whose semester has already started and whose status
     * is still 'active' or 'finalized'.
     */
    public function archiveExpired(): void
    {
        $expiredSemesterIds = Semester::query()
            ->where('starts_at', '<=', Carbon::today())
            ->pluck('semester_id')
            ->all();

        if (empty($expiredSemesterIds)) {
            return;
        }

        Workplan::query()
            ->whereIn('semester_id', $expiredSemesterIds)
            ->whereIn('status', ['active', 'finalized'])
            ->update(['status' => 'archived']);
    }
}
