<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectricitySale extends Model
{
    protected $fillable = [
        'property_id',
        'unit_id',
        'sale_date',
        'units_sold',
        'unit_price',
        'amount',
        'notes',
        'recorded_by',
        'invoice_id',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'units_sold' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
