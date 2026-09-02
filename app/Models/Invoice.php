<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Company/property/tenant/unit data shared by the two invoice PDF renderers
     * (InvoiceController::downloadPdf and ProformaInvoiceMail::generatePdf) — kept
     * here so the two stay in sync instead of duplicating this assembly logic.
     */
    public function buildPdfViewData(\Illuminate\Support\Collection $items): array
    {
        $companyName  = SystemSetting::get('company_name', 'Mwamba Properties');
        $companyEmail = SystemSetting::get('support_email', '');
        $vatNumber    = SystemSetting::get('vat_number', '');
        $tinNumber    = SystemSetting::get('tin_number', '');
        $companyReg   = SystemSetting::get('company_registration', '');

        $property        = $this->property_id ? Property::find($this->property_id) : null;
        $companyPhone    = $property?->phone ?? '';
        $bankName        = $property?->bank_name ?? '';
        $bankAccount     = $property?->bank_account ?? '';
        $bankAccountName = $property?->bank_account_name ?? '';
        $swiftCode       = $property?->swift_code ?? '';

        $tenant   = null;
        $tenantId = null;
        $vatRate  = 0;
        $unit     = null;

        if ($this->lease_id) {
            $lease = $this->relationLoaded('lease') ? $this->lease : $this->load('lease')->lease;
            if ($lease) {
                $tenantId = $lease->tenant_id;
                $vatRate  = (float) ($lease->vat_rate ?? 0);
                $tenant   = Tenant::find($tenantId);
                $unit     = $lease->unit_id ? Unit::find($lease->unit_id) : null;
            }
        }
        if (!$tenant && $this->tenant_email) {
            $tenant   = Tenant::where('email', $this->tenant_email)->first();
            $tenantId = $tenantId ?? $tenant?->id;
        }

        $unitLine = null;
        if ($unit) {
            $sizeSqm  = $unit->size_sqm ? rtrim(rtrim(number_format((float) $unit->size_sqm, 2), '0'), '.') : null;
            $unitLine = trim(sprintf(
                '%sFloor %s Office No: %s%s',
                $property?->name ? "{$property->name} — " : '',
                $unit->floor ?: '-',
                $unit->unit_number,
                $sizeSqm ? " sq.mt: {$sizeSqm}" : ''
            ));
        }

        $isRentServiceOnly = $items->isNotEmpty()
            && $items->every(fn ($i) => in_array($i->item_type ?? '', ['rent', 'service_charge'], true));

        $companyLogoBase64 = null;
        $logoPath = SystemSetting::get('company_logo', '');
        if ($logoPath) {
            $fullPath = Storage::disk('public')->path($logoPath);
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) ?: 'png';
                // dompdf needs GD (or Imagick) to embed raster images (png/jpg/webp) —
                // SVG is rendered by dompdf's own vector renderer and doesn't need either.
                $canEmbed = $ext === 'svg' || extension_loaded('gd') || extension_loaded('imagick');
                if ($canEmbed) {
                    $mime = $ext === 'svg' ? 'svg+xml' : $ext;
                    $companyLogoBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
                }
            }
        }

        $approvedByName = $this->relationLoaded('approvalDecidedBy')
            ? $this->approvalDecidedBy?->name
            : $this->load('approvalDecidedBy')->approvalDecidedBy?->name;

        return [
            'companyName'       => $companyName,
            'companyEmail'      => $companyEmail,
            'companyPhone'      => $companyPhone,
            'vatNumber'         => $vatNumber,
            'tinNumber'         => $tinNumber,
            'companyReg'        => $companyReg,
            'companyLogoBase64' => $companyLogoBase64,
            'property'          => $property,
            'tenantUnit'        => $this->unit_ref ?? '',
            'tenantId'          => $tenantId,
            'tenantPhone'       => $tenant?->phone ?? '',
            'tenantTin'         => $tenant?->tin ?? '',
            'tenantVrn'         => $tenant?->vrn ?? '',
            'tenantAddress'     => $tenant?->address ?? '',
            'tenantCity'        => $tenant?->city ?? '',
            'bankName'          => $bankName,
            'bankAccount'       => $bankAccount,
            'bankAccountName'   => $bankAccountName,
            'swiftCode'         => $swiftCode,
            'vatRate'           => $vatRate,
            'unitLine'          => $unitLine,
            'isRentServiceOnly' => $isRentServiceOnly,
            'approvedByName'    => $approvedByName ?? '',
        ];
    }
}
