<?php

namespace App\Enums;

final class OrganizationType
{
    public const SOCIO_CIVIC = 1;

    public const RELIGIOUS = 2;

    public const FRATERNITIES_SORORITIES = 3;

    public const SPECIAL_INTEREST = 4;

    public const UNIVERSITY_SANCTIONED = 5;

    public const SUPREME_STUDENT_COUNCIL = 6;

    public const COLLEGE_STUDENT_COUNCIL = 7;

    public const ACADEMIC = 8;

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
