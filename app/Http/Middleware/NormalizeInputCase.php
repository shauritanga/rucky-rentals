<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Emails are always stored lower case and VRN / VAT numbers upper case, whatever was typed
 * (the forms already do this while typing; this covers anything that reaches the server).
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
                } elseif (in_array($key, self::UPPER, true)) {
                    $input[$key] = mb_strtoupper(trim($value));
                }
            }
        }

        return $input;
    }
}
