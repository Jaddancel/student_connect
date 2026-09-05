<?php

namespace App\Services;

use App\Models\EventPlan;
use App\Models\Approval;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\Workplan;
use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
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
     * Whether the organization has an admin-approved workplan for the
     * semester containing the supplied event date.
     */
    public function hasApprovedWorkplanForDate(int $organizationId, string $targetDate): bool
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $semester = Semester::query()
            ->where('starts_at', '<=', $date)
            ->orderByDesc('starts_at')
            ->get()
            ->first(fn (Semester $semester) => ($endsAt = $semester->endsAt()) === null || $date->lte($endsAt));

        if ($semester === null) {
            return false;
        }

        $workplan = Workplan::query()
            ->where('organization_id', $organizationId)
            ->where('semester_id', (int) $semester->getKey())
            ->where('status', 'finalized')
            ->first();

        if ($workplan === null) {
            return false;
        }

        $form = SystemFunction::form(SystemFunction::NEW_WORKPLAN);
        if ($form === null) {
            return false;
        }

        $submissionIds = FormSubmission::query()
            ->where('form_id', (int) $form->getKey())
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(fn (FormSubmission $submission) => (int) (($submission->payload ?? [])['semester_id'] ?? 0) === (int) $semester->getKey())
            ->pluck('form_submission_id');

        if ($submissionIds->isEmpty()) {
            return false;
        }

        $requestIds = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(fn (ActionRequest $request) => $submissionIds->contains((int) (($request->payload ?? [])['submission_id'] ?? 0)))
            ->pluck('request_id');

        return $requestIds->isNotEmpty()
            && Approval::query()
                ->whereIn('request', $requestIds)
                ->where('is_rejected', false)
                ->exists();
    }

    /**
     * Returns approved EventPlans for the workplan's org whose target_date
     * falls within the semester's date range.
     *
     * Approved only: an activity enters the workplan when the admin approves
     * its event request, not when the officer submits one. Pending plans are
     * still awaiting that decision and rejected ones never made it.
     */
    public function getApprovedPlansForWorkplan(Workplan $workplan): Collection
    {
        $workplan->loadMissing('semester');
        $semester = $workplan->semester;

        $query = EventPlan::query()
            ->where('organization_id', $workplan->organization_id)
            ->where('status', 'approved')
            ->whereNull('parent_plan_id')
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
