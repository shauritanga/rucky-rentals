<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitClearance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'lease_id',
        'unit_id',
        'tenant_id',
        'clearance_number',
        'status',
        'scheduled_date',
        'inspected_by_user_id',
        'inspected_at',
        'inspection_checklist',
        'currency',
        'deposit_amount',
        'total_deductions',
        'refund_amount',
        'shortfall_amount',
        'manager_notes',
        'cancelled_reason',
        'finalized_by',
        'finalized_at',
        'created_by',
    ];

    protected $casts = [
        'inspection_checklist' => 'array',
        'inspected_at'         => 'datetime',
        'finalized_at'         => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(UnitClearanceItem::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'unit_clearance_id');
    }

    /**
     * Server-side source of truth for the financial summary — never trust
     * client-supplied totals, since these numbers drive the GL posting.
     */
    public function recalculateTotals(): void
    {
        $deductions = (float) $this->items()->where('responsible_party', 'tenant')->sum('cost');
        $deposit    = (float) $this->deposit_amount;

        $this->total_deductions = $deductions;
        $this->refund_amount    = max(0, $deposit - $deductions);
        $this->shortfall_amount = max(0, $deductions - $deposit);
        $this->save();
    }
}
