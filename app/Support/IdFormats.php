<?php

namespace App\Support;

/**
 * Identity document and TIN formats. Numbers are stored without separators
 * (digits, or upper-case letters/digits for passports); the UI shows them masked.
 */
class IdFormats
{
    public const TYPES = [
        'national'        => ['label' => 'National ID (NIDA)', 'pattern' => '/^\d{20}$/',          'hint' => '20 digits'],
        'voter'           => ['label' => 'Voter ID',           'pattern' => '/^\d{12}$/',          'hint' => '12 digits'],
        'passport'        => ['label' => 'Passport',           'pattern' => '/^[A-Z0-9]{6,9}$/',   'hint' => '6 to 9 letters or digits'],
        'driving_license' => ['label' => 'Driving Licence',    'pattern' => '/^\d{10}$/',          'hint' => '10 digits'],
    ];

    public static function label(?string $type): string
    {
        return self::TYPES[$type]['label'] ?? 'ID';
    }

    /** Strip separators; passports are upper-cased, everything else keeps digits only. */
    public static function normalize(?string $type, ?string $value): string
    {
        $value = (string) $value;
        if ($type === 'passport') {
            return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
        }

        return preg_replace('/\D/', '', $value);
    }

    /** Error message when the number does not fit the type, otherwise null. */
    public static function problem(?string $type, string $normalized): ?string
    {
        $def = self::TYPES[$type] ?? null;
        if (!$def) {
            return 'Choose an ID type.';
        }

        return preg_match($def['pattern'], $normalized)
            ? null
            : "{$def['label']} must be {$def['hint']}.";
    }

    public static function normalizeTin(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    public static function tinProblem(string $normalized): ?string
    {
        return preg_match('/^\d{9}$/', $normalized) ? null : 'TIN must be 9 digits (for example 123-456-789).';
    }
}
