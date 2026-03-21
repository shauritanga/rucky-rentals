<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'unit_number',
        'floor',
        'type',
        'size_sqft',
        'size_sqm',
        'rate_per_sqm',
        'currency',
        'rent',
        'status',
        'deposit',
        'notes',
    ];

    protected $casts = [
        'size_sqm' => 'float',
        'rate_per_sqm' => 'float',
        'rent' => 'float',
        'deposit' => 'float',
    ];

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function meterReadings()
    {
        return $this->hasMany(MeterReading::class);
    }
    public function maintenanceTickets()
    {
        return $this->hasMany(MaintenanceTicket::class);
    }
    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
