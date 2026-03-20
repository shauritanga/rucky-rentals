<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'id_number', 'nok_name', 'nok_phone', 'nok_relationship', 'status',
    ];

    public function leases() { return $this->hasMany(Lease::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
