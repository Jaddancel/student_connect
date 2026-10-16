<?php

namespace App\Enums;

final class OrganizationType
{
    public const SOCIO_CIVIC = 1;
    public const RELIGIOUS = 2;
    public const FRATERNITIES_SORORITIES = 3;
    public const SPECIAL_INTEREST = 4;
    public const UNIVERSITY_SANCTIONED = 5;
    public const STUDENT_GOVERNMENT = 6;

    public static function label(int $type): string
    {
        return self::options()[$type] ?? 'Other';
    }

    /**
     * Every organization type as value => label, in declaration order — the
     * option set for the "Organization Type" picker field.
     *
     * @return array<int,string>
     */
    public static function options(): array
    {
        return config('organization_types', []);
    }
}
