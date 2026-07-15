<?php

namespace App\Services\Scoring;

use Illuminate\Validation\ValidationException;

/**
 * Whitelist validation for a scoring rule's compiled trigger AST (the shape
 * {@see ScoringRuleEngine} evaluates). The Blockly editor compiles blocks
 * client-side; the server never trusts that output — anything outside this
 * grammar is rejected on save.
 *
 * Grammar:
 *   trigger  := {when, if?, then}
 *   when     := {source: form_submission|event_plan, form_id?: int>0}
 *   if       := logic | compare | null
 *   logic    := {op: and|or, children: if[1..10]} | {op: not, children: if[1]}
 *   compare  := {op: =|!=|>|>=|<|<=|contains|not_empty, left: {var}, right?: {value}}
 *   then.add := {kind: const, value: int 1..1000}
 *             | {kind: floor_div, var, divisor: number > 0}
 *             | {kind: count_list, var}
 *   var      := "field:<key>" | "universal:<key>" | "plan:<column>"
 */
class TriggerValidator
{
    private const SOURCES = ['form_submission', 'event_plan'];

    private const LOGIC_OPS = ['and', 'or', 'not'];

    private const COMPARE_OPS = ['=', '!=', '>', '>=', '<', '<=', 'contains', 'not_empty'];

    private const MAX_DEPTH = 6;

    /**
     * @param  array<string,mixed>  $trigger
     *
     * @throws ValidationException
     */
    public function validate(array $trigger): void
    {
        $when = $trigger['when'] ?? null;
        if (! is_array($when) || ! in_array($when['source'] ?? null, self::SOURCES, true)) {
            $this->fail('The rule must start with a "when" block (form submission or event plan).');
        }

        if (($when['source'] === 'form_submission')
            && (! isset($when['form_id']) || (int) $when['form_id'] <= 0)) {
            $this->fail('A form-submission trigger must name the form it watches.');
        }

        $if = $trigger['if'] ?? null;
        if ($if !== null) {
            $this->validateCondition($if, 1);
        }

        $add = $trigger['then']['add'] ?? null;
        if (! is_array($add)) {
            $this->fail('The rule must end with an "add instances" block.');
        }

        match ($add['kind'] ?? null) {
            'const' => (is_numeric($add['value'] ?? null) && (int) $add['value'] >= 1 && (int) $add['value'] <= 1000)
                || $this->fail('The "add" amount must be a whole number between 1 and 1000.'),
            'floor_div' => ($this->validateVar($add['var'] ?? null) && is_numeric($add['divisor'] ?? null) && (float) $add['divisor'] > 0)
                || $this->fail('A "per amount" add needs a variable and a positive divisor.'),
            'count_list' => $this->validateVar($add['var'] ?? null)
                || $this->fail('A "count rows" add needs a list variable.'),
            default => $this->fail('Unknown "add" kind.'),
        };
    }

    /**
     * @param  mixed  $node
     */
    private function validateCondition($node, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $this->fail('Conditions are nested too deeply.');
        }
        if (! is_array($node)) {
            $this->fail('Malformed condition block.');
        }

        $op = $node['op'] ?? null;

        if (in_array($op, self::LOGIC_OPS, true)) {
            $children = $node['children'] ?? null;
            if (! is_array($children) || $children === [] || count($children) > 10) {
                $this->fail('Logic blocks need between 1 and 10 nested conditions.');
            }
            if ($op === 'not' && count($children) !== 1) {
                $this->fail('A "not" block takes exactly one condition.');
            }
            foreach ($children as $child) {
                $this->validateCondition($child, $depth + 1);
            }

            return;
        }

        if (! in_array($op, self::COMPARE_OPS, true)) {
            $this->fail('Unknown condition operator.');
        }

        if (! $this->validateVar($node['left']['var'] ?? null)) {
            $this->fail('Each comparison must reference a variable.');
        }

        if ($op !== 'not_empty') {
            $right = $node['right'] ?? null;
            if (! is_array($right) || ! array_key_exists('value', $right) || is_array($right['value'])) {
                $this->fail('Each comparison needs a scalar value to compare against.');
            }
        }
    }

    private function validateVar(mixed $var): bool
    {
        return is_string($var)
            && preg_match('/^(field|universal|plan):[A-Za-z0-9_]{1,100}$/', $var) === 1;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['trigger' => $message]);
    }
}
