<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'type',
        'property_id',
        'lease_id',
        'tenant_name',
        'tenant_email',
        'unit_ref',
        'issued_date',
        'due_date',
        'period',
        'status',
        'notes',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
