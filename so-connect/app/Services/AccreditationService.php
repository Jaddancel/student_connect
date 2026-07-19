<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * The deadline being ENFORCED right now: the start of the current
     * (already-started) semester. Compliance for disabling is measured against
     * this date, whereas {@see deadline()} (the next upcoming start) drives the
     * pre-deadline warnings. Null until a semester has started.
     */
    public function enforcementDeadline(): ?Carbon
    {
        return Semester::current()?->starts_at?->copy()->startOfDay();
    }

    /**
     * True once an enforcement deadline exists and has arrived — i.e. a semester
     * has started, so non-compliant orgs may be disabled.
     */
    public function deadlineHasPassed(?Carbon $now = null): bool
    {
        $enforcementDeadline = $this->enforcementDeadline();

        return $enforcementDeadline !== null
            && ($now ?? Carbon::today())->startOfDay()->greaterThanOrEqualTo($enforcementDeadline);
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
        return \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::ORG_ACCREDITATION)
            ?? Form::query()
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

    /**
     * Days left until the deadline for a member who should see the pre-deadline
     * danger card — i.e. we are inside the warning window and at least one of
     * their (still-active) orgs is not yet compliant. Null means "no warning".
     */
    public function warningDaysLeftForUser(int $userId, ?Carbon $now = null): ?int
    {
        if (! $this->isWithinWarningWindow($now)) {
            return null;
        }

        $orgIds = \App\Services\OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        if ($orgIds === []) {
            return null;
        }

        $hasNonCompliant = Organization::query()
            ->whereIn('organization_id', $orgIds)
            ->accreditationActive()
            ->get()
            ->contains(fn (Organization $org) => ! $this->isCompliant($org));

        return $hasNonCompliant ? $this->daysUntilDeadline($now) : null;
    }

    // ------------------------------------------------------------------ lifecycle

    /**
     * Disable an org for missing accreditation (idempotent). Stamps the time so
     * the purge grace period can be measured from it.
     */
    public function disable(Organization $org): void
    {
        if ($org->isAccreditationDisabled()) {
            return;
        }

        $org->forceFill([
            'accreditation_status' => self::STATUS_DISABLED,
            'accreditation_disabled_at' => now(),
        ])->save();
    }

    /**
     * Restore a disabled org (super-admin action before the purge deadline).
     */
    public function restore(Organization $org): void
    {
        $org->forceFill([
            'accreditation_status' => self::STATUS_ACTIVE,
            'accreditation_disabled_at' => null,
        ])->save();
    }

    /**
     * Active orgs that should be disabled now: the deadline has passed and they
     * are not compliant. Empty until a deadline exists and has passed.
     *
     * @return Collection<int,Organization>
     */
    public function orgsToDisable(?Carbon $now = null): Collection
    {
        $enforcementDeadline = $this->enforcementDeadline();
        if ($enforcementDeadline === null || ! $this->deadlineHasPassed($now)) {
            return collect();
        }

        return Organization::query()
            ->accreditationActive()
            ->get()
            ->filter(fn (Organization $org) => ! $this->isCompliant($org, $enforcementDeadline))
            ->values();
    }

    /**
     * Disabled orgs whose grace period has elapsed (eligible for hard purge).
     *
     * @return Collection<int,Organization>
     */
    public function orgsToPurge(?Carbon $now = null): Collection
    {
        $now = ($now ?? Carbon::today())->startOfDay();

        return Organization::query()
            ->where('accreditation_status', self::STATUS_DISABLED)
            ->whereNotNull('accreditation_disabled_at')
            ->get()
            ->filter(function (Organization $org) use ($now) {
                $eligibleAt = $this->purgeEligibleAt($org->accreditation_disabled_at);

                return $eligibleAt !== null && $now->greaterThanOrEqualTo($eligibleAt);
            })
            ->values();
    }

    /**
     * Hard-delete an organization and its entire subtree, child-first.
     *
     * FK checks are toggled off inside the transaction so the (self-referential)
     * event_plans tree and the full grandchild graph — approvals,
     * generated_documents, template_descriptions, evaluations, presidents — can
     * be removed without hand-ordering three levels of dependents. Everything is
     * scoped to this org's ids, so nothing outside its subtree is touched.
     */
    public function purge(Organization $org): void
    {
        $orgId = (int) $org->getKey();
        $detailId = $org->detail;

        DB::transaction(function () use ($orgId, $detailId) {
            $requestIds = ActionRequest::query()->where('organization_id', $orgId)->pluck('request_id')->all();
            $submissionIds = DB::table('form_submissions')->where('organization_id', $orgId)->pluck('form_submission_id')->all();
            $templateIds = DB::table('templates')->where('organization_id', $orgId)->pluck('id')->all();
            $officerIds = DB::table('organization_officers')->where('organization', $orgId)->pluck('org_officer_id')->all();

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                // Grandchildren
                if ($requestIds !== []) {
                    DB::table('approvals')->whereIn('request', $requestIds)->delete();
                    DB::table('generated_documents')->whereIn('request_id', $requestIds)->delete();
                }
                if ($submissionIds !== []) {
                    DB::table('generated_documents')->whereIn('form_submission_id', $submissionIds)->delete();
                }
                if ($templateIds !== []) {
                    DB::table('generated_documents')->whereIn('template_id', $templateIds)->delete();
                    DB::table('template_descriptions')->whereIn('template_id', $templateIds)->delete();
                }
                if ($officerIds !== []) {
                    DB::table('evaluations')->whereIn('author', $officerIds)->delete();
                    DB::table('presidents')->whereIn('officer', $officerIds)->delete();
                }

                // Direct children
                DB::table('event_plans')->where('organization_id', $orgId)->orWhere('related_to_organization', $orgId)->delete();
                DB::table('events')->where('organization', $orgId)->delete();
                DB::table('workplans')->where('organization_id', $orgId)->delete();
                DB::table('form_submissions')->where('organization_id', $orgId)->delete();
                DB::table('requests')->where('organization_id', $orgId)->delete();
                DB::table('templates')->where('organization_id', $orgId)->delete();
                DB::table('organization_scores')->where('organization_id', $orgId)->delete();
                DB::table('organization_officers')->where('organization', $orgId)->delete();
                DB::table('posts')->where('organization', $orgId)->delete();
                // Org-specific forms only (global forms have a null organization_id).
                DB::table('forms')->where('organization_id', $orgId)->delete();

                // The org and its detail row.
                DB::table('organizations')->where('organization_id', $orgId)->delete();
                if ($detailId) {
                    DB::table('organization_details')->where('organization_detail_id', $detailId)->delete();
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    }
}
