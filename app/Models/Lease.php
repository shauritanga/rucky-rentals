<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'unit_id',
        'start_date',
        'end_date',
        'duration_months',
        'payment_cycle',
        'currency',
        'possession_date',
        'rent_start_date',
        'fitout_enabled',
        'fitout_to_date',
        'fitout_days',
        'wht_rate',
        'service_charge_rate',
        'vat_rate',
        'monthly_rent',
        'deposit',
        'terms',
        'status',
        'approval_log',
    ];

    protected $casts = [
        'approval_log'   => 'array',
        'fitout_enabled' => 'boolean',
        'fitout_days'    => 'integer',
    ];

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

    public function installments()
    {
        return $this->hasMany(LeaseInstallment::class)->orderBy('sequence');
    }
}
