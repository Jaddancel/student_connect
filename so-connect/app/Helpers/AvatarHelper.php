<?php

namespace App\Helpers;

class AvatarHelper
{
    /**
     * @return array{0:string,1:string}
     */
    public static function colorClassesForName(?string $name): array
    {
        $palettes = [
            ['bg-rose-100', 'text-rose-700'],
            ['bg-amber-100', 'text-amber-700'],
            ['bg-lime-100', 'text-lime-700'],
            ['bg-emerald-100', 'text-emerald-700'],
            ['bg-cyan-100', 'text-cyan-700'],
            ['bg-sky-100', 'text-sky-700'],
            ['bg-indigo-100', 'text-indigo-700'],
            ['bg-fuchsia-100', 'text-fuchsia-700'],
        ];

        $normalized = strtolower(trim((string) $name));

        if ($normalized === '') {
            return $palettes[0];
        }

        $index = crc32($normalized) % count($palettes);

        return $palettes[$index];
    }

    public static function initialFromName(?string $name): string
    {
        $normalized = trim((string) $name);

        if ($normalized === '') {
            return '?';
        }

        return strtoupper(substr($normalized, 0, 1));
    }
}
