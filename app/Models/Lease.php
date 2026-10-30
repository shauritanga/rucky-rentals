<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lease_number',
        'property_id',
        'tenant_id',
        'unit_id',
        'start_date',
        'end_date',
        'duration_months',
        'payment_cycle',
        'currency',
        'exchange_rate',
        'possession_date',
        'rent_start_date',
        'fitout_enabled',
        'fitout_to_date',
        'fitout_days',
        'wht_rate',
        'service_charge_rate',
        'vat_rate',
        'monthly_rent',
        'service_charge',
        'deposit',
        'terms',
        'status',
        'approval_log',
        'co_tenant_group',
    ];

    protected $appends = ['display_rate', 'monthly_service_charge'];

    protected $casts = [
        'exchange_rate'  => 'float',
        'approval_log'   => 'array',
        'fitout_enabled' => 'boolean',
        'fitout_days'    => 'integer',
    ];

    /**
     * USD→TZS rate used only to *show* a USD-unit lease in TZS: the latest rate typed on one of
     * its proformas, else the deposit rate typed at approval. Never applied to real billing.
     */
    public function getDisplayRateAttribute(): ?float
    {
        if (strtoupper((string) $this->currency) !== 'USD') {
            return null;
        }

        $invoiceRate = Invoice::where('lease_id', $this->id)->whereNotNull('usd_rate')->orderByDesc('id')->value('usd_rate');

        return $invoiceRate ? (float) $invoiceRate : ($this->exchange_rate ? (float) $this->exchange_rate : null);
    }

    /** This lease's monthly service charge: its stored co-tenant share, else the unit's full charge. */
    public function getMonthlyServiceChargeAttribute(): float
    {
        return (float) ($this->service_charge ?? $this->unit?->service_charge ?? 0);
    }

    /** This lease and the leases created together with it for the same co-tenant unit. */
    public function groupMembers()
    {
        if (!$this->co_tenant_group) {
            return collect([$this]);
        }

        return static::where('co_tenant_group', $this->co_tenant_group)->orderBy('id')->get();
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function installments()
    {
        return $this->hasMany(LeaseInstallment::class)->orderBy('sequence');
    }

    public function clearances()
    {
        return $this->hasMany(UnitClearance::class);
    }
}
