<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return (static::all_values()[$key] ?? null) ?? $default;
    }

    /**
     * Every setting, read with one query per request. A page often asks for a dozen settings;
     * this keeps that to a single round trip. The array cache store is per request (and per test).
     */
    private static function all_values(): array
    {
        $store = \Illuminate\Support\Facades\Cache::store('array');
        $all = $store->get('system_settings.all');
        if ($all === null) {
            $all = static::query()->pluck('value', 'key')->all();
            $store->forever('system_settings.all', $all);
        }

        return $all;
    }

    /**
     * Set (upsert) a setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        \Illuminate\Support\Facades\Cache::store('array')->forget('system_settings.all');
    }
}
