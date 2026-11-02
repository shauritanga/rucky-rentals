<?php

namespace App\Support;

/**
 * Phone numbers are stored in international form: "+255712345678".
 * Older records ("0712 345 678", "255712345678") are converted the same way the forms do.
 */
class PhoneNumber
{
    private const DEFAULT_DIAL = '255';

    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return $raw === '' ? null : $raw; // not a number: leave it for validation to reject
        }

        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            return '+' . self::DEFAULT_DIAL . ltrim($digits, '0');
        }
        if (str_starts_with($digits, self::DEFAULT_DIAL) && strlen($digits) >= 11) {
            return '+' . $digits;
        }
        if (strlen($digits) === 9) {
            return '+' . self::DEFAULT_DIAL . $digits;
        }

        return '+' . $digits;
    }
}
