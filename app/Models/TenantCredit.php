<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantCredit extends Model
{
    protected $fillable = [
        'tenant_id', 'property_id', 'amount', 'type', 'source_invoice_id', 'target_invoice_id',
        'payment_id', 'method', 'reference', 'note', 'created_by',
    ];

    protected $casts = ['amount' => 'float'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function targetInvoice()
    {
        return $this->belongsTo(Invoice::class, 'target_invoice_id');
    }
}
