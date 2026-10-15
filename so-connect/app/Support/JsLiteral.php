<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Encodes PHP data as a JavaScript literal that is valid both inside a
 * <script> block and inside an HTML attribute, and that Alpine's CSP build
 * can evaluate.
 *
 * Laravel's Js::from() / @js wrap arrays and objects in JSON.parse('…'),
 * which the CSP build refuses (JSON is a global outside Alpine's scope).
 * This emits the value directly as an object/array literal instead. Strings
 * are single-quoted with every quote, <, >, & and non-ASCII character
 * \u-escaped, so the output never contains a double quote or markup and
 * cannot break out of an attribute or a script element.
 */
final class JsLiteral implements Htmlable
{
    public function __construct(private readonly mixed $data) {}

    public static function from(mixed $data): self
    {
        return new self($data);
    }

    public function toHtml(): string
    {
        return self::encode($this->data);
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Extra arguments (Js::from's $flags, $depth) are accepted for drop-in
     * compatibility with @js and ignored.
     */
    public static function encode(mixed $data, mixed ...$ignored): string
    {
        // Round-trip through JSON so Arrayable/JsonSerializable/enums/models
        // serialize exactly as they would for Js::from(); objects decode to
        // stdClass and lists to arrays, keeping {} and [] distinct.
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        return self::literal(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
    }

    private static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            is_string($value) => self::string($value),
            is_array($value) => '['.implode(',', array_map(self::literal(...), $value)).']',
            default => '{'.implode(',', array_map(
                fn ($key, $item) => self::string((string) $key).':'.self::literal($item),
                array_keys((array) $value),
                array_values((array) $value),
            )).'}',
        };
    }

    private static function number(float $value): string
    {
        $json = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);

        // The CSP parser has no exponent syntax; coerce such numbers from a string.
        return str_contains(strtolower($json), 'e') ? "(+'{$json}')" : $json;
    }

    private static function string(string $value): string
    {
        $json = json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        );

        // Escapes the CSP parser does not understand become \u sequences.
        $body = preg_replace_callback('/\\\\(.)/s', fn (array $m) => match ($m[1]) {
            'b' => '\u0008',
            'f' => '\u000c',
            default => $m[0],
        }, substr($json, 1, -1));

        return "'".$body."'";
    }
}
