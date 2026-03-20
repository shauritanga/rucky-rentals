<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'number', 'floor', 'type', 'size', 'rent', 'status', 'amenities',
    ];

    public function leases() { return $this->hasMany(Lease::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function meterReadings() { return $this->hasMany(MeterReading::class); }
    public function maintenanceTickets() { return $this->hasMany(MaintenanceTicket::class); }
    public function documents() { return $this->hasMany(Document::class); }
}
