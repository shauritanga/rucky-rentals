<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentChangeRequest extends Model
{
    protected $fillable = [
        'payment_id', 'property_id', 'type', 'payload', 'status',
        'requested_by', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'payload'    => 'array',
        'decided_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
