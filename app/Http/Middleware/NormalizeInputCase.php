<?php

namespace App\Http\Middleware;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Http\Request;

/**
 * Emails are always stored lower case, VRN / VAT numbers upper case and phone numbers in international
 * form (+255712345678), whatever was typed. The forms already do this while typing; this covers anything
 * that reaches the server.
 */
class NormalizeInputCase
{
    private const UPPER = ['vrn', 'vat_number'];

    public function handle(Request $request, Closure $next)
    {
        $request->merge($this->normalize($request->all()));

        return $next($request);
    }

    private function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->normalize($value);
            } elseif (is_string($value) && is_string($key)) {
                if ($key === 'email' || str_ends_with($key, '_email')) {
                    $input[$key] = mb_strtolower(trim($value));
                } elseif ($key === 'phone' || str_ends_with($key, '_phone')) {
                    $input[$key] = PhoneNumber::normalize($value);
                } elseif (in_array($key, self::UPPER, true)) {
                    $input[$key] = mb_strtoupper(trim($value));
                }
            }
        }

        return $input;
    }
}
