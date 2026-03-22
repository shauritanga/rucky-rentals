<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ExchangeRate extends Model
{
    protected $fillable = [
        'property_id',
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
     * Get the active exchange rate for a property on a specific date
     */
    public static function getRate(
        int $propertyId,
        string $fromCurrency,
        string $toCurrency,
        \DateTimeInterface|string $date
    ): ?float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $normalizedDate = self::normalizeDate($date);

        $rate = self::where('property_id', $propertyId)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $normalizedDate)
            ->orderByDesc('effective_date')
            ->pluck('rate')
            ->first();

        // Fallback for backdated documents when only newer rates are configured.
        if ($rate === null) {
            $rate = self::where('property_id', $propertyId)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->orderByDesc('effective_date')
                ->pluck('rate')
                ->first();
        }

        return $rate;
    }

    /**
     * Convert amount from one currency to another
     * Returns null if rate not found
     */
    public static function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        int $propertyId,
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
