<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'property_id',
        'invoice_id',
        'tenant_id',
        'unit_id',
        'month',
        'amount',
        'method',
        'status',
        'paid_date',
        'reference',
        'date',
        'notes',
        'currency',
        'exchange_rate',
        'amount_in_base',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
