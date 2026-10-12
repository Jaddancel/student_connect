<?php

namespace Database\Seeders\Support;

use App\Models\Form;
use App\Models\ScoringRule;
use App\Services\Scoring\ScoringRuleEngine;
use RuntimeException;

final class SeedScoring
{
    /** @return array{payload: array, linked: array, criterion: ?string} */
    public static function target(Form $form, array $payload, array $linked = []): array
    {
        $sourceFormId = $form->system_function === 'new_event'
            ? Form::query()->where('system_function', 'after_event_report')->value('id')
            : $form->getKey();
        $rules = ScoringRule::query()->with('criterion')->where('enabled', true)->get()
            ->filter(fn ($rule) => (int) ($rule->trigger['when']['form_id'] ?? 0) === (int) $sourceFormId
                && $rule->criterion?->is_active)->shuffle();
        $isNewEvent = $form->system_function === 'new_event';
        foreach ($rules as $rule) {
            $values = $isNewEvent ? [
                'number_of_organization_members_attended' => 60,
                'actual_duration_of_time_in_minutes' => 120,
                'individual_awards' => 2,
                'group_awards' => 2,
                'were_there_any_awards' => 1,
            ] : $payload;
            $source = $isNewEvent ? $payload : $linked;
            try {
                self::satisfy($rule->trigger['if'] ?? null, $values, $source);
                $add = $rule->trigger['then']['add'] ?? [];
                if (($add['kind'] ?? '') === 'floor_div') {
                    [$prefix, $key] = explode(':', $add['var'], 2);
                    if ($prefix === 'field') {
                        $values[$key] = max((float) ($values[$key] ?? 0), (float) $add['divisor']);
                    }
                }
            } catch (UnseedableRule) {
                continue;
            }
            $record = ['values' => $values, 'linked_values' => $source, 'user_id' => 0];
            if (app(ScoringRuleEngine::class)->tallyRecord($rule->trigger, $record) > 0) {
                return [
                    'payload' => $isNewEvent ? $source : SeedPayload::calculate($form, $values),
                    'linked' => $source,
                    'criterion' => $rule->criterion->key,
                ];
            }
        }
        if ($rules->isNotEmpty()) {
            throw new RuntimeException('No configured scoring criterion can be satisfied for '.$form->name);
        }

        return ['payload' => $payload, 'linked' => $linked, 'criterion' => null];
    }

    private static function satisfy(?array $node, array &$values, array &$linked, bool $truth = true): void
    {
        if ($node === null) {
            return;
        }
        $op = $node['op'];
        $children = $node['children'] ?? [];
        if ($op === 'not') {
            self::satisfy($children[0], $values, $linked, ! $truth);

            return;
        }
        if ($op === 'and' || $op === 'or') {
            $selected = ($op === 'and') === $truth ? $children : [fake()->randomElement($children)];
            foreach ($selected as $child) {
                self::satisfy($child, $values, $linked, $truth);
            }

            return;
        }
        [$prefix, $key] = explode(':', $node['left']['var'], 2);
        if ($prefix === 'new_event') {
            if ($linked === [] || ! array_key_exists($key, $linked)) {
                throw new UnseedableRule;
            }
            $target = &$linked;
        } elseif ($prefix === 'field') {
            if (! array_key_exists($key, $values)) {
                throw new UnseedableRule;
            }
            $target = &$values;
        } else {
            throw new RuntimeException('Unsupported seed scoring variable: '.$node['left']['var']);
        }
        $right = $node['right']['value'] ?? null;
        $target[$key] = match ($op) {
            '=' => $truth ? $right : self::different($right),
            '>=' => $truth ? $right : (float) $right - 1,
            '>' => $truth ? (float) $right + 1 : $right,
            default => throw new RuntimeException('Unsupported seed scoring operator: '.$op),
        };
    }

    private static function different(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return (int) $value === 0 ? 1 : 0;
        }
        if (is_string($value) && str_starts_with($value, 'option_')) {
            return $value === 'option_1' ? 'option_2' : 'option_1';
        }

        throw new UnseedableRule;
    }
}
