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
        return match ($type) {
            self::SOCIO_CIVIC => 'Socio-Civic',
            self::RELIGIOUS => 'Religious',
            self::FRATERNITIES_SORORITIES => 'Fraternities-Sororities',
            self::SPECIAL_INTEREST => 'Special Interest',
            self::UNIVERSITY_SANCTIONED => 'University-Sanctioned',
            self::STUDENT_GOVERNMENT => 'Student Government',
            default => 'Other',
        };
    }

    /**
     * Every organization type as value => label, in declaration order — the
     * option set for the "Organization Type" picker field.
     *
     * @return array<int,string>
     */
    public static function options(): array
    {
        return [
            self::SOCIO_CIVIC => self::label(self::SOCIO_CIVIC),
            self::RELIGIOUS => self::label(self::RELIGIOUS),
            self::FRATERNITIES_SORORITIES => self::label(self::FRATERNITIES_SORORITIES),
            self::SPECIAL_INTEREST => self::label(self::SPECIAL_INTEREST),
            self::UNIVERSITY_SANCTIONED => self::label(self::UNIVERSITY_SANCTIONED),
            self::STUDENT_GOVERNMENT => self::label(self::STUDENT_GOVERNMENT),
        ];
    }
}
