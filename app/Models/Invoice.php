<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'lease_id', 'number', 'date', 'due_date', 'status', 'notes',
    ];

    public function lease() { return $this->belongsTo(Lease::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
}
