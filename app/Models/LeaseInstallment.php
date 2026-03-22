<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseInstallment extends Model
{
    protected $fillable = [
        'property_id',
        'lease_id',
        'invoice_id',
        'sequence',
        'period_start',
        'period_end',
        'due_date',
        'amount',
        'currency',
        'status',
        'paid_amount',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
