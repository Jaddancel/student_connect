<?php

namespace App\Forms;

use Illuminate\Support\Facades\DB;

/**
 * Resolves id-valued special fields (organization / event / workplan /
 * workplan-events) to human-readable labels for the printed PDF. Non-numeric
 * values (e.g. the "[Field]" placeholders used in admin preview) pass through
 * unchanged.
 */
final class SpecialFieldLabel
{
    /** @var array<string,string> */
    private static array $orgs = [];

    /** @var array<int,string> */
    private static array $plans = [];

    /** @var array<int,string> */
    private static array $plansWithDate = [];

    public static function forField(string $type, mixed $value): string
    {
        return match ($type) {
            FieldType::ORG_SELECT => self::organizationName($value),
            FieldType::ORGANIZATION_TYPE_SELECT => is_numeric($value)
                ? \App\Enums\OrganizationType::label((int) $value)
                : (string) $value,
            FieldType::EVENT_SELECT => self::eventPlanTitle($value),
            FieldType::WORKPLAN_SELECT => implode(', ', self::workplanActivityLabels($value)),
            FieldType::WORKPLAN_EVENTS => implode(', ', array_filter(array_map(
                fn ($id) => self::eventPlanTitle($id),
                (array) $value,
            ))),
            default => is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value,
        };
    }

    private static function organizationName(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }
        $id = (string) (int) $value;

        if (! array_key_exists($id, self::$orgs)) {
            $row = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('o.organization_id', (int) $id)
                ->value('od.name');
            self::$orgs[$id] = (string) ($row ?? '');
        }

        return self::$orgs[$id];
    }

    private static function eventPlanTitle(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }
        $id = (int) $value;

        if (! array_key_exists($id, self::$plans)) {
            self::$plans[$id] = (string) (DB::table('event_plans')
                ->where('event_plan_id', $id)
                ->value('title') ?? '');
        }

        return self::$plans[$id];
    }

    /**
     * "Title — Date" for a table `event-select` column's stored event plan id
     * (used by the printed PDF and the admin review table — the live form
     * draws the same label from {@see SpecialFieldData}'s resolved event list).
     */
    public static function eventPlanTitleWithDate(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }
        $id = (int) $value;

        if (! array_key_exists($id, self::$plansWithDate)) {
            $row = DB::table('event_plans')->where('event_plan_id', $id)->first(['title', 'target_date']);
            $title = (string) ($row->title ?? '');
            $date = $row && $row->target_date ? self::formatDate((string) $row->target_date) : '';
            self::$plansWithDate[$id] = trim($title.($date !== '' ? ' — '.$date : ''));
        }

        return self::$plansWithDate[$id];
    }

    /**
     * The approved activities of a workplan-picker's stored workplan id, in
     * date order — the same list the live form previews under the picker.
     * Resolved live (not cached) so a long-running worker prints activities
     * approved since it booted.
     *
     * @return array<int,array{title:string,date:string}>
     */
    public static function workplanActivities(mixed $value): array
    {
        if (! is_numeric($value)) {
            return [];
        }

        $workplan = \App\Models\Workplan::query()->with('semester')->find((int) $value);
        if ($workplan === null || $workplan->semester === null) {
            return [];
        }

        return app(\App\Services\WorkplanService::class)
            ->getApprovedPlansForWorkplan($workplan)
            ->map(fn ($plan) => [
                'title' => (string) $plan->title,
                'date' => $plan->target_date ? self::formatDate((string) $plan->target_date) : '',
            ])
            ->values()
            ->all();
    }

    /**
     * "Title — Date" per approved activity of a workplan-picker value. A
     * non-numeric value (admin preview placeholder) passes through as-is.
     *
     * @return array<int,string>
     */
    public static function workplanActivityLabels(mixed $value): array
    {
        if (! is_numeric($value)) {
            return ($value === null || $value === '') ? [] : [(string) $value];
        }

        return array_map(
            fn (array $activity) => trim($activity['title'].($activity['date'] !== '' ? ' — '.$activity['date'] : '')),
            self::workplanActivities($value),
        );
    }

    private static function formatDate(string $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('F j, Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
