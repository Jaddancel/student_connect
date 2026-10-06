<?php

namespace App\Services\Scoring;

use App\Models\Form;
use App\Support\UniversalField;

/**
 * Renders a scoring trigger AST as a plain-English sentence for the rules list,
 * e.g. "When a submission of «New Event» is approved, if members_attended > 15,
 * add 1 instance." Read-only presentation — the editor owns authoring.
 */
final class TriggerSummary
{
    private const OP_WORDS = [
        '=' => 'is', '!=' => 'is not', '>' => '>', '>=' => '≥', '<' => '<', '<=' => '≤',
        'contains' => 'contains', 'not_empty' => 'is filled in',
    ];

    /** @var array<int,string>|null */
    private static ?array $formNames = null;

    /**
     * @param  array<string,mixed>|null  $trigger
     */
    public static function text(?array $trigger): string
    {
        if (! is_array($trigger) || empty($trigger)) {
            return 'No trigger — this criterion scores 0.';
        }

        $when = (array) ($trigger['when'] ?? []);
        $source = ($when['source'] ?? '') === 'event_plan'
            ? 'an event plan'
            : 'a submission of “'.self::formName((int) ($when['form_id'] ?? 0)).'”';

        $sentence = 'When '.$source.' is approved, ';

        $if = $trigger['if'] ?? null;
        if ($if) {
            $sentence .= 'if '.self::condition($if).', ';
        }

        $sentence .= 'add '.self::adder((array) ($trigger['then']['add'] ?? [])).'.';

        return $sentence;
    }

    /**
     * @param  array<string,mixed>  $node
     */
    private static function condition(array $node): string
    {
        $op = $node['op'] ?? null;

        if ($op === 'and' || $op === 'or') {
            $parts = array_map(fn ($c) => self::condition((array) $c), (array) ($node['children'] ?? []));

            return '('.implode($op === 'and' ? ' and ' : ' or ', $parts).')';
        }
        if ($op === 'not') {
            return 'not '.self::condition((array) (($node['children'] ?? [])[0] ?? []));
        }

        $var = self::varLabel((string) ($node['left']['var'] ?? ''));
        $word = self::OP_WORDS[$op] ?? (string) $op;
        if ($op === 'not_empty') {
            return $var.' '.$word;
        }

        return $var.' '.$word.' '.(string) ($node['right']['value'] ?? '');
    }

    /**
     * @param  array<string,mixed>  $add
     */
    private static function adder(array $add): string
    {
        return match ($add['kind'] ?? null) {
            'const' => ((int) ($add['value'] ?? 1)).' instance(s)',
            'floor_div' => '1 instance per count of '.self::varLabel((string) ($add['var'] ?? '')).' divided by '.(string) ($add['divisor'] ?? 1),
            'count_list' => '1 instance per row of '.self::varLabel((string) ($add['var'] ?? '')),
            default => 'nothing',
        };
    }

    private static function varLabel(string $var): string
    {
        if (str_starts_with($var, 'universal:')) {
            $key = substr($var, strlen('universal:'));
            $meta = UniversalField::get($key);

            return $meta['label'] ?? $key;
        }
        if (str_starts_with($var, 'new_event:')) {
            return 'New Event '.substr($var, strlen('new_event:'));
        }
        // field:<key> / plan:<key> — the bare key reads clearly enough.
        $parts = explode(':', $var, 2);

        return $parts[1] ?? $var;
    }

    private static function formName(int $formId): string
    {
        if (self::$formNames === null) {
            self::$formNames = Form::query()->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        }

        return self::$formNames[$formId] ?? 'a form';
    }
}
