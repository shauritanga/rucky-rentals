<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'email',
        'phone',
        'national_id',
        'initials',
        'color',
        'text_color',
        'nok_name',
        'nok_phone',
        'nok_relation',
        'notes',
        'tenant_type',
        'company_name',
        'registration_number',
        'tin',
        'vrn',
        'contact_person',
        'address',
        'city',
        'country',
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
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
