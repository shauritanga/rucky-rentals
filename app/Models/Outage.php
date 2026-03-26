<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outage extends Model
{
    protected $fillable = [
        'property_id', 'outage_date', 'start_time', 'end_time', 'type',
        'floors_affected', 'generator_activated', 'fuel_used', 'notes',
    ];

    protected $casts = ['generator_activated' => 'boolean'];

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Property::class);
    }
}
