<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    protected $fillable = [
        'property_id',
        'tenant_id',
        'unit_id',
        'start_date',
        'end_date',
        'duration_months',
        'payment_cycle',
        'currency',
        'monthly_rent',
        'deposit',
        'terms',
        'status',
        'approval_log',
    ];

    protected $casts = ['approval_log' => 'array'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
