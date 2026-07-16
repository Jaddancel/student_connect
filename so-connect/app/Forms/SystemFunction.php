<?php

namespace App\Forms;

use App\Forms\Handlers\MembershipRegistrationHandler;
use App\Forms\Handlers\NewEventHandler;
use App\Forms\Handlers\NewWorkplanHandler;
use App\Forms\Handlers\SignUpHandler;
use App\Forms\Handlers\SystemFunctionHandler;
use App\Models\Form;

/**
 * Registry of the fixed system functions a builder form page can be bound to
 * (forms.system_function). Mirrors {@see \App\Support\UniversalField}'s
 * code-defined-catalog style.
 *
 * Binding does two things:
 *  1. `form($key)` resolves "the form that serves this purpose" everywhere the
 *     app used to hardcode a seeded route_name (the `legacy_route` fallback
 *     keeps behavior identical until an admin binds a replacement or the
 *     seeded forms are wiped).
 *  2. Submissions of a bound form are dispatched to the function's
 *     {@see SystemFunctionHandler}, which routes them into the
 *     request-approval lifecycle (request + document stored, admin approves →
 *     domain write, rejects → the user starts over).
 */
final class SystemFunction
{
    public const SIGN_UP = 'sign_up';

    public const NEW_EVENT = 'new_event';

    public const NEW_WORKPLAN = 'new_workplan';

    public const MEMBERSHIP_REGISTRATION = 'membership_registration';

    /**
     * @return array<string, array{label:string, description:string, legacy_route:?string, handler:class-string<SystemFunctionHandler>}>
     */
    public static function catalog(): array
    {
        return [
            self::SIGN_UP => [
                'label' => 'Sign Up (new officer account)',
                'description' => 'Submissions request a new user account (user type 3); an admin approval creates the account.',
                'legacy_route' => 'student-leader-directory',
                'handler' => SignUpHandler::class,
            ],
            self::NEW_EVENT => [
                'label' => 'New Event',
                'description' => 'Submissions request a new event; an admin approval schedules it.',
                'legacy_route' => 'activity-request',
                'handler' => NewEventHandler::class,
            ],
            self::NEW_WORKPLAN => [
                'label' => 'New Workplan',
                'description' => 'Submissions request a workplan document; an admin approval generates it.',
                'legacy_route' => 'workplan',
                'handler' => NewWorkplanHandler::class,
            ],
            self::MEMBERSHIP_REGISTRATION => [
                'label' => 'Org Membership Registration',
                'description' => 'Submissions request membership in an organization; an admin (or the org president) approves it.',
                'legacy_route' => null,
                'handler' => MembershipRegistrationHandler::class,
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    public static function label(string $key): string
    {
        return self::catalog()[$key]['label'] ?? $key;
    }

    /**
     * The form serving a system function: the explicitly bound form, or the
     * seeded legacy form while one still exists. Null when neither does — the
     * caller decides how the flow degrades.
     */
    public static function form(string $key): ?Form
    {
        $meta = self::catalog()[$key] ?? null;
        if ($meta === null) {
            return null;
        }

        $bound = Form::query()->where('system_function', $key)->first();
        if ($bound) {
            return $bound;
        }

        return $meta['legacy_route']
            ? Form::query()->where('route_name', $meta['legacy_route'])->first()
            : null;
    }

    /**
     * Like {@see form()}, but fails with an actionable validation message when
     * no form serves the function (e.g. after the seeded forms were wiped and
     * before an admin bound a replacement).
     */
    public static function formOrFail(string $key): Form
    {
        $form = self::form($key);
        if ($form === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form' => 'No form is bound to the "'.self::label($key).'" function — bind one in the Form Builder first.',
            ]);
        }

        return $form;
    }

    /**
     * The submission handler for a bound form, or null when the form is not
     * bound to any function.
     */
    public static function handlerForForm(Form $form): ?SystemFunctionHandler
    {
        $key = (string) ($form->system_function ?? '');
        $meta = self::catalog()[$key] ?? null;

        return $meta ? app($meta['handler']) : null;
    }
}
