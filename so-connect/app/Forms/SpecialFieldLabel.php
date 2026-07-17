<?php

namespace App\Forms;

use Illuminate\Support\Facades\DB;

/**
 * Resolves id-valued special fields (organization / event / workplan-events)
 * to human-readable labels for the printed PDF. Non-numeric values (e.g. the
 * "[Field]" placeholders used in admin preview) pass through unchanged.
 */
final class SpecialFieldLabel
{
    /** @var array<string,string> */
    private static array $orgs = [];

    /** @var array<int,string> */
    private static array $plans = [];

    public static function forField(string $type, mixed $value): string
    {
        return match ($type) {
            FieldType::ORG_SELECT => self::organizationName($value),
            FieldType::EVENT_SELECT => self::eventPlanTitle($value),
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
}
