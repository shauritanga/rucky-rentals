<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    protected $fillable = [
        'property_id', // nullable — rates are global, property_id kept for legacy data only
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'rate' => 'float',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the global exchange rate for a currency pair on a specific date.
     * Rates are not property-scoped — they are international/global.
     *
     * @param int|null  $propertyId  Ignored — kept for backwards-compatible call sites only
     */
    public static function getRate(
        int|null $propertyId,
        string $fromCurrency,
        string $toCurrency,
        \DateTimeInterface|string $date
    ): ?float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $normalizedDate = self::normalizeDate($date);

        // Most recent rate on or before the given date
        $rate = self::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $normalizedDate)
            ->orderByDesc('effective_date')
            ->pluck('rate')
            ->first();

        // Fallback: any rate for this pair in DB (e.g. only future rates exist)
        if ($rate === null) {
            $rate = self::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->orderByDesc('effective_date')
                ->pluck('rate')
                ->first();
        }

        // Fallback: use the live-API cache populated by the /exchange-rate endpoint
        if ($rate === null) {
            $cacheKey = sprintf('exchange_rate:%s:%s', strtoupper($fromCurrency), strtoupper($toCurrency));
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $rate = (float) $cached;
            }
        }

        // Final fallback: use the configured default rate (e.g. 2650 for USD/TZS)
        if ($rate === null) {
            $rate = (float) config('app.fx_fallback_rate', 2650);
        }

        return $rate;
    }

    /**
     * Get the current live exchange rate for DISPLAY purposes only.
     *
     * Reads from the Laravel cache populated by the /exchange-rate API endpoint,
     * which is the same source the UI header rate badge uses. This guarantees
     * every dashboard figure uses the same rate the user sees on screen.
     *
     * DO NOT use this for accounting/GL posting — use getRate($date) instead,
     * which reads historical DB rates for immutable, auditable transaction records.
     */
    public static function getLiveRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = sprintf('exchange_rate:%s:%s', strtoupper($fromCurrency), strtoupper($toCurrency));
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (float) $cached;
        }

        return (float) config('app.fx_fallback_rate', 2650);
    }

    /**
     * Convert amount from one currency to another
     * Returns null if rate not found
     */
    public static function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        int|null $propertyId,
        \DateTimeInterface|string $date
    ): ?float {
        $rate = self::getRate($propertyId, $fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    private static function normalizeDate(\DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }
}
