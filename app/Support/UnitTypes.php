<?php

namespace App\Support;

class UnitTypes
{
    public const OTHER = 'Other';

    public const DEFAULTS = [
        'Apartment',
        'Office Suite',
        'Retail Shop',
        'Warehouse',
        'Store',
    ];

    /**
     * Merge a property's custom saved types with the global defaults,
     * deduped case-insensitively, defaults first, "Other" always last.
     */
    public static function merge(?array $custom): array
    {
        $custom = array_values(array_filter(array_map('trim', $custom ?? []), fn ($v) => $v !== ''));

        $seen = [];
        $result = [];

        foreach (array_merge(self::DEFAULTS, $custom) as $type) {
            $key = mb_strtolower($type);
            if ($key === mb_strtolower(self::OTHER) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $type;
        }

        $result[] = self::OTHER;

        return $result;
    }
}
