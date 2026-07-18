<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use Illuminate\Support\Carbon;

/**
 * Central authority for the organization-accreditation cycle.
 *
 * This first slice covers the deadline/window/grace maths that everything else
 * (danger cards, the enforce command, the conditions editor) reads from. The
 * compliance-condition evaluation (accreditation.conditions AST) and the
 * disable/purge lifecycle are layered on top once their shape is settled.
 *
 * Timeline:
 *   … deadline − notify_days ──[warning window]── deadline ──[grace]── purge
 *   deadline        = the next upcoming semester's start date
 *   notify_days     = AppSetting accreditation.notify_days       (default 7)
 *   grace (days)    = AppSetting accreditation.purge_grace_days  (default 30)
 */
class AccreditationService
{
    public const DEFAULT_NOTIFY_DAYS = 7;

    public const DEFAULT_PURGE_GRACE_DAYS = 30;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    /**
     * The accreditation deadline: the start date of the next upcoming semester,
     * or null when no future semester is scheduled (nothing to enforce yet).
     */
    public function deadline(): ?Carbon
    {
        $next = Semester::query()->upcoming()->first();

        return $next?->starts_at?->copy()->startOfDay();
    }

    /**
     * How many days before the deadline the warning window opens (type-2
     * configurable via Settings).
     */
    public function warningWindowDays(): int
    {
        return max(1, (int) AppSetting::get('accreditation.notify_days', self::DEFAULT_NOTIFY_DAYS));
    }

    /**
     * Grace period, in days, between an org being disabled and being purged.
     */
    public function purgeGraceDays(): int
    {
        return max(0, (int) AppSetting::get('accreditation.purge_grace_days', self::DEFAULT_PURGE_GRACE_DAYS));
    }

    /**
     * Whole days from $now until the deadline (negative once it has passed);
     * null when no deadline is scheduled.
     */
    public function daysUntilDeadline(?Carbon $now = null): ?int
    {
        $deadline = $this->deadline();
        if (! $deadline) {
            return null;
        }

        return ($now ?? Carbon::today())->startOfDay()->diffInDays($deadline, false);
    }

    /**
     * True while today sits inside the warning window: on or after
     * (deadline − notify_days) and on or before the deadline itself.
     */
    public function isWithinWarningWindow(?Carbon $now = null): bool
    {
        $days = $this->daysUntilDeadline($now);

        return $days !== null && $days >= 0 && $days <= $this->warningWindowDays();
    }

    /**
     * True once the deadline has passed (enforcement — disable — may run).
     */
    public function deadlineHasPassed(?Carbon $now = null): bool
    {
        $days = $this->daysUntilDeadline($now);

        return $days !== null && $days < 0;
    }

    /**
     * The date a disabled org becomes eligible for hard purge
     * (disabled_at + grace), or null if it is not disabled.
     */
    public function purgeEligibleAt(?Carbon $disabledAt): ?Carbon
    {
        return $disabledAt?->copy()->addDays($this->purgeGraceDays())->startOfDay();
    }

    // ------------------------------------------------------------------ compliance

    /**
     * The accreditation "conditions" AST. For now this is deliberately small:
     * `required_forms` is the set of form ids whose APPROVED submission (before
     * the deadline) makes an org compliant. Stored under
     * AppSetting['accreditation.conditions']; edited from Settings.
     *
     * @return array<string,mixed>
     */
    public function conditions(): array
    {
        return (array) AppSetting::get('accreditation.conditions', []);
    }

    /**
     * Form ids that must each have an approved, before-deadline submission for an
     * org to be accredited. Falls back to the single seeded accreditation form
     * when nothing is configured, so the rule is meaningful out of the box.
     *
     * @return array<int,int>
     */
    public function requiredFormIds(): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($this->conditions()['required_forms'] ?? []),
        ))));

        if ($ids !== []) {
            return $ids;
        }

        $default = $this->defaultAccreditationForm();

        return $default ? [(int) $default->getKey()] : [];
    }

    /**
     * The default qualifying form: the one bound to the accreditation system
     * function once that exists, otherwise the seeded accreditation/recognition
     * page resolved by route.
     */
    public function defaultAccreditationForm(): ?Form
    {
        return Form::query()
            ->whereIn('route_name', ['organization-accreditation', 'organization-recognition'])
            ->orderByRaw("route_name = 'organization-accreditation' desc")
            ->first();
    }

    /**
     * Whole rule check: an org is compliant when every required form has an
     * approved submission on or before the deadline (per product decision #9,
     * only an approved entry counts — pending/rejected do not).
     */
    public function isCompliant(Organization $org, ?Carbon $deadline = null): bool
    {
        return $this->missingFormIds($org, $deadline) === [];
    }

    /**
     * Required form ids the org has NOT satisfied (empty ⇒ compliant). When no
     * forms are required at all, the org is treated as compliant.
     *
     * @return array<int,int>
     */
    public function missingFormIds(Organization $org, ?Carbon $deadline = null): array
    {
        $required = $this->requiredFormIds();
        if ($required === []) {
            return [];
        }

        $deadline ??= $this->deadline();
        $orgId = (int) $org->getKey();

        return array_values(array_filter(
            $required,
            fn (int $formId) => ! $this->hasApprovedSubmission($orgId, $formId, $deadline),
        ));
    }

    /**
     * A summary of an org's accreditation standing, for danger cards / the
     * (later) enforce command.
     *
     * @return array{compliant:bool, missing_form_ids:array<int,int>, deadline:?string, days_until_deadline:?int, within_window:bool, deadline_passed:bool}
     */
    public function evaluate(Organization $org, ?Carbon $now = null): array
    {
        $missing = $this->missingFormIds($org);

        return [
            'compliant' => $missing === [],
            'missing_form_ids' => $missing,
            'deadline' => $this->deadline()?->toDateString(),
            'days_until_deadline' => $this->daysUntilDeadline($now),
            'within_window' => $this->isWithinWarningWindow($now),
            'deadline_passed' => $this->deadlineHasPassed($now),
        ];
    }

    /**
     * True when the org has an approved (non-rejected) request for $formId that
     * was submitted on or before $deadline. Matches the form id whether it was
     * stored on the request column or only in the submission payload.
     */
    private function hasApprovedSubmission(int $orgId, int $formId, ?Carbon $deadline): bool
    {
        $approvedRequestIds = Approval::query()
            ->select('request')
            ->where('is_rejected', false)
            ->whereNotNull('request');

        return ActionRequest::query()
            ->where('organization_id', $orgId)
            ->where(fn ($q) => $q->where('form_id', $formId)->orWhere('payload->form_id', $formId))
            ->when($deadline, fn ($q) => $q->where('requested_at', '<=', $deadline))
            ->whereIn('request_id', $approvedRequestIds)
            ->exists();
    }
}
