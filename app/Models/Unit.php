<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'requested_by_user_id',
        'unit_number',
        'floor',
        'type',
        'size_sqft',
        'size_sqm',
        'rate_per_sqm',
        'service_charge_per_sqm',
        'currency',
        'rent',
        'status',
        'deposit',
        'service_charge',
        'electricity_type',
        'notes',
        'approval_status',
        'approval_requested_at',
        'approval_decided_at',
        'approval_note',
    ];

    protected $casts = [
        'size_sqm'               => 'float',
        'rate_per_sqm'           => 'float',
        'service_charge_per_sqm' => 'float',
        'rent'                   => 'float',
        'deposit'                => 'float',
        'service_charge'         => 'float',
        'approval_requested_at'  => 'datetime',
        'approval_decided_at'    => 'datetime',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function meterReadings()
    {
        return $this->hasMany(MeterReading::class);
    }
    public function latestMeterReading()
    {
        return $this->hasOne(MeterReading::class)->ofMany([
            'reading_date' => 'max',
            'id' => 'max',
        ]);
    }
    public function electricitySales()
    {
        return $this->hasMany(ElectricitySale::class);
    }
    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
    public function maintenanceTickets()
    {
        return $this->maintenanceRecords();
    }
    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
