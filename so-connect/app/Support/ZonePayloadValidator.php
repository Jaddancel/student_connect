<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Validates + normalizes a template's zone payload (id or waiver). Each zone is
 * a named rectangle in native image pixels with a type; waiver templates may
 * additionally use the 'stamp' (embossed dry-seal) type.
 */
class ZonePayloadValidator
{
    /**
     * @return array<int,string>
     */
    public static function types(): array
    {
        return (array) config('waiver.zone_types', ['text', 'signature', 'stamp']);
    }

    /**
     * @param  array<int,mixed>  $zones
     * @return array<int,array{name:string, field:string, type:string, x:int, y:int, w:int, h:int}>
     *
     * @throws ValidationException
     */
    public static function validate(array $zones): array
    {
        $clean = [];
        $seen = [];

        foreach ($zones as $index => $zone) {
            $where = 'zone #'.($index + 1);
            if (! is_array($zone)) {
                self::fail("{$where} is malformed.");
            }

            $name = trim((string) ($zone['name'] ?? ''));
            if ($name === '') {
                self::fail("{$where} needs a name.");
            }
            if (isset($seen[$name])) {
                self::fail("Duplicate zone name \"{$name}\".");
            }
            $seen[$name] = true;

            $type = (string) ($zone['type'] ?? 'text');
            if (! in_array($type, self::types(), true)) {
                self::fail("{$where} has an unknown type \"{$type}\".");
            }

            foreach (['x', 'y', 'w', 'h'] as $key) {
                if (! isset($zone[$key]) || ! is_numeric($zone[$key])) {
                    self::fail("{$where} is missing a numeric \"{$key}\".");
                }
            }
            if ((float) $zone['w'] <= 0 || (float) $zone['h'] <= 0) {
                self::fail("{$where} must have a positive width and height.");
            }

            $clean[] = [
                'name' => $name,
                'field' => trim((string) ($zone['field'] ?? '')) ?: $name,
                'type' => $type,
                'x' => (int) round((float) $zone['x']),
                'y' => (int) round((float) $zone['y']),
                'w' => (int) round((float) $zone['w']),
                'h' => (int) round((float) $zone['h']),
            ];
        }

        return $clean;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['zones' => $message]);
    }
}
