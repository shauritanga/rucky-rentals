<?php

namespace App\Support;

class FloorConfig
{
    /**
     * Normalize a raw floor_config value (array, JSON string, or null)
     * into a canonical associative array with all keys present.
     */
    public static function parse(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'basements'        => max(0, (int) ($raw['basements'] ?? 0)),
            'has_ground_floor' => (bool) ($raw['has_ground_floor'] ?? false),
            'has_mezzanine'    => (bool) ($raw['has_mezzanine'] ?? false),
            'upper_floors'     => max(1, (int) ($raw['upper_floors'] ?? 7)),
        ];
    }

    /**
     * Generate the ordered floor list from a parsed config.
     *
     * Returns an array of floor objects:
     *   ['id' => string, 'label' => string, 'sort_order' => int]
     *
     * Sort order:
     *   B{n} → n * -100   (B1=-100, B2=-200 — sorted deepest-first)
     *   G    → 0
     *   M    → 50
     *   {n}  → n * 100
     */
    public static function floors(array $config): array
    {
        $floors = [];

        // Basements: deepest first (B2 before B1)
        for ($n = $config['basements']; $n >= 1; $n--) {
            $floors[] = [
                'id'         => "B{$n}",
                'label'      => "Basement {$n}",
                'sort_order' => $n * -100,
            ];
        }

        if ($config['has_ground_floor']) {
            $floors[] = ['id' => 'G', 'label' => 'Ground Floor', 'sort_order' => 0];
        }

        if ($config['has_mezzanine']) {
            $floors[] = ['id' => 'M', 'label' => 'Mezzanine', 'sort_order' => 50];
        }

        for ($n = 1; $n <= $config['upper_floors']; $n++) {
            $floors[] = [
                'id'         => (string) $n,
                'label'      => "Floor {$n}",
                'sort_order' => $n * 100,
            ];
        }

        return $floors;
    }

    /**
     * Return the sort order integer for a single floor code.
     * Used for PHP-side collection sorting.
     */
    public static function sortOrder(string $code): int
    {
        if ($code === '') {
            return PHP_INT_MAX;
        }
        if ($code === 'G') {
            return 0;
        }
        if ($code === 'M') {
            return 50;
        }
        if (str_starts_with($code, 'B')) {
            $n = (int) substr($code, 1);
            return $n > 0 ? $n * -100 : PHP_INT_MAX;
        }
        if (is_numeric($code)) {
            return (int) $code * 100;
        }
        return PHP_INT_MAX;
    }

    /**
     * Return a flat array of valid floor ID strings for the given config.
     * Used for Rule::in() validation.
     */
    public static function floorIds(array $config): array
    {
        return array_column(self::floors($config), 'id');
    }

    /**
     * Check whether a floor code is valid for the given config.
     */
    public static function isValid(string $code, array $config): bool
    {
        return in_array($code, self::floorIds($config), true);
    }
}
