<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'id_type',
        'email',
        'phone',
        'national_id',
        'initials',
        'invoice_code',
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

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant) {
            if ($tenant->tenant_type === 'individual' && ($tenant->isDirty('first_name') || $tenant->isDirty('middle_name') || $tenant->isDirty('last_name'))) {
                $tenant->name = trim(preg_replace('/\s+/', ' ', ($tenant->first_name ?? '') . ' ' . ($tenant->middle_name ?? '') . ' ' . ($tenant->last_name ?? '')));
            }

            if (empty($tenant->invoice_code)) {
                $tenant->invoice_code = static::generateInvoiceCode(
                    $tenant->company_name ?: (string) $tenant->name,
                    $tenant->id
                );
            }
        });
    }

    /**
     * Short, unique invoice code from a tenant name's initials
     * (TANZANIA INSTITUTE OF ARBITRATORS => TIA). Duplicates get a numeric suffix.
     */
    public static function generateInvoiceCode(string $name, ?int $ignoreId = null): string
    {
        $skip = ['OF', 'AND', 'THE', 'FOR', '&'];
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => !in_array($w, $skip, true)));

        $base = count($words) > 1
            ? implode('', array_map(fn ($w) => $w[0], $words))
            : substr($words[0] ?? 'TNT', 0, 4);
        $base = substr($base, 0, 8) ?: 'TNT';

        $code = $base;
        $n = 2;
        while (static::where('invoice_code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base . $n++;
        }

        return $code;
    }

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
