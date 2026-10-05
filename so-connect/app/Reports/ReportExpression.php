<?php

namespace App\Reports;

use InvalidArgumentException;

/**
 * The arithmetic behind a "compute" token: numbers, the names of sibling value
 * tokens, + - * / %, unary minus and parentheses. Parsed by a small recursive
 * descent parser into an AST and evaluated directly — never handed to eval().
 *
 * AST nodes: ['n', float] | ['v', name] | ['neg', node] | ['op', '+|-|*|/|%', left, right]
 */
final class ReportExpression
{
    public const MAX_LENGTH = 200;

    /** @var array<int, array{0:string, 1:string}> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @return array<int, mixed>
     *
     * @throws InvalidArgumentException when the expression is empty or malformed
     */
    public static function parse(string $expression): array
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException('enter a formula.');
        }
        if (mb_strlen($expression) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('formula is longer than '.self::MAX_LENGTH.' characters.');
        }

        $parser = new self;
        $parser->tokens = $parser->tokenize($expression);
        $ast = $parser->expression();
        if ($parser->pos < count($parser->tokens)) {
            throw new InvalidArgumentException('unexpected "'.$parser->tokens[$parser->pos][1].'".');
        }

        return $ast;
    }

    /**
     * Distinct token names the expression reads, in order of appearance.
     *
     * @param  array<int, mixed>  $ast
     * @return array<int, string>
     */
    public static function references(array $ast): array
    {
        $found = [];
        $walk = function (array $node) use (&$walk, &$found): void {
            match ($node[0]) {
                'v' => $found[$node[1]] = true,
                'neg' => $walk($node[1]),
                'op' => [$walk($node[2]), $walk($node[3])],
                default => null,
            };
        };
        $walk($ast);

        return array_keys($found);
    }

    /**
     * Evaluate against name => number. Returns null when the result is
     * undefined (division by zero, or a variable that is not numeric).
     *
     * @param  array<int, mixed>  $ast
     * @param  array<string, float|int|null>  $values  null reads as 0
     */
    public static function evaluate(array $ast, array $values): ?float
    {
        switch ($ast[0]) {
            case 'n':
                return $ast[1];
            case 'v':
                return array_key_exists($ast[1], $values) ? ($values[$ast[1]] ?? 0.0) : null;
            case 'neg':
                $inner = self::evaluate($ast[1], $values);

                return $inner === null ? null : -$inner;
        }

        $left = self::evaluate($ast[2], $values);
        $right = self::evaluate($ast[3], $values);
        if ($left === null || $right === null) {
            return null;
        }

        return match ($ast[1]) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0 ? null : $left / $right,
            '%' => $right == 0 ? null : fmod($left, $right),
        };
    }

    // ── Parser ─────────────────────────────────────────────────────────────

    /** @return array<int, array{0:string, 1:string}> */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        for ($i = 0; $i < $length;) {
            $char = $source[$i];
            if (ctype_space($char)) {
                $i++;

                continue;
            }
            if (preg_match('/\G(\d+(?:\.\d+)?|\.\d+)/', $source, $m, 0, $i)) {
                $tokens[] = ['num', $m[1]];
                $i += strlen($m[1]);
            } elseif (preg_match('/\G[a-z][a-z0-9_]*/', $source, $m, 0, $i)) {
                $tokens[] = ['name', $m[0]];
                $i += strlen($m[0]);
            } elseif (str_contains('+-*/%()', $char)) {
                $tokens[] = ['sym', $char];
                $i++;
            } else {
                throw new InvalidArgumentException('unexpected "'.$char.'" — use numbers, token names, + - * / % and parentheses.');
            }
        }

        return $tokens;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function symbol(string ...$symbols): ?string
    {
        $token = $this->peek();
        if ($token !== null && $token[0] === 'sym' && in_array($token[1], $symbols, true)) {
            $this->pos++;

            return $token[1];
        }

        return null;
    }

    /** @return array<int, mixed> */
    private function expression(): array
    {
        $node = $this->term();
        while (($op = $this->symbol('+', '-')) !== null) {
            $node = ['op', $op, $node, $this->term()];
        }

        return $node;
    }

    /** @return array<int, mixed> */
    private function term(): array
    {
        $node = $this->unary();
        while (($op = $this->symbol('*', '/', '%')) !== null) {
            $node = ['op', $op, $node, $this->unary()];
        }

        return $node;
    }

    /** @return array<int, mixed> */
    private function unary(): array
    {
        if ($this->symbol('-') !== null) {
            return ['neg', $this->unary()];
        }
        if ($this->symbol('+') !== null) {
            return $this->unary();
        }

        return $this->primary();
    }

    /** @return array<int, mixed> */
    private function primary(): array
    {
        $token = $this->peek();
        if ($token === null) {
            throw new InvalidArgumentException('the formula ends unexpectedly.');
        }

        if ($token[0] === 'num') {
            $this->pos++;

            return ['n', (float) $token[1]];
        }
        if ($token[0] === 'name') {
            $this->pos++;

            return ['v', $token[1]];
        }
        if ($this->symbol('(') !== null) {
            $node = $this->expression();
            if ($this->symbol(')') === null) {
                throw new InvalidArgumentException('a parenthesis is not closed.');
            }

            return $node;
        }

        throw new InvalidArgumentException('unexpected "'.$token[1].'".');
    }
}
