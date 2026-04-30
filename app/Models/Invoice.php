<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'type',
        'property_id',
        'requested_by_user_id',
        'lease_id',
        'tenant_name',
        'tenant_email',
        'unit_ref',
        'issued_date',
        'due_date',
        'period',
        'status',
        'approval_status',
        'approval_requested_at',
        'approval_decided_at',
        'approval_decided_by',
        'approval_note',
        'sent_to_tenant_at',
        'sent_to_tenant_by',
        'notes',
        'currency',
        'exchange_rate',
        'total_in_base',
    ];

    protected $casts = [
        'approval_requested_at' => 'datetime',
        'approval_decided_at' => 'datetime',
        'sent_to_tenant_at' => 'datetime',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvalDecidedBy()
    {
        return $this->belongsTo(User::class, 'approval_decided_by');
    }

    public function sentToTenantBy()
    {
        return $this->belongsTo(User::class, 'sent_to_tenant_by');
    }
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    /**
     * Calculate invoice total from line items
     */
    public function getTotal(): float
    {
        return (float) ($this->items()->sum('total') ?? 0.0);
    }

    public function isEditable(): bool
    {
        return $this->type === 'proforma' && $this->sent_to_tenant_at === null;
    }
}
