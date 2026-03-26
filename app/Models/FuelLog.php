<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    protected $fillable = [
        'property_id', 'log_date', 'litres', 'price_per_litre', 'total_cost',
        'supplier', 'level_after', 'recorded_by',
    ];

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Property::class);
    }
}
