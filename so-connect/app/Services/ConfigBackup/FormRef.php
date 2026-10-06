<?php

namespace App\Services\ConfigBackup;

use App\Models\Form;

/**
 * Forms are referenced across environments by natural key instead of DB id:
 * the route_name (URL slug), falling back to the system_function binding for
 * forms without one. Both columns are unique.
 */
final class FormRef
{
    /** @return array{route_name: ?string, system_function: ?string}|null */
    public static function of(?Form $form): ?array
    {
        if ($form === null || (blank($form->route_name) && blank($form->system_function))) {
            return null;
        }

        return [
            'route_name' => $form->route_name ?: null,
            'system_function' => $form->system_function ?: null,
        ];
    }

    public static function find(mixed $ref): ?Form
    {
        if (! is_array($ref)) {
            return null;
        }

        $route = $ref['route_name'] ?? null;
        if (is_string($route) && $route !== '') {
            $form = Form::query()->where('route_name', $route)->first();
            if ($form) {
                return $form;
            }
        }

        $function = $ref['system_function'] ?? null;
        if (is_string($function) && $function !== '') {
            return Form::query()->where('system_function', $function)->first();
        }

        return null;
    }

    public static function label(mixed $ref): string
    {
        if (! is_array($ref)) {
            return 'unknown form';
        }

        return (string) (($ref['route_name'] ?? null) ?: ($ref['system_function'] ?? null) ?: 'unknown form');
    }
}
