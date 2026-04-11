<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProformaInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Collection $items,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Proforma Invoice ' . $this->invoice->invoice_number . ' — Ruky Rentals',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proforma-invoice',
        );
    }

    public function attachments(): array
    {
        try {
            $pdfContent = $this->generatePdf();
            $filename   = $this->invoice->invoice_number . '.pdf';

            return [
                \Illuminate\Mail\Mailables\Attachment::fromData(
                    fn () => $pdfContent,
                    $filename,
                )->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            \Log::warning('ProformaInvoiceMail: PDF generation failed — ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function generatePdf(): string
    {
        $invoice  = $this->invoice;
        $items    = $this->items;

        // Company-level (global) settings
        $companyName  = SystemSetting::get('company_name', 'Ruky Rentals');
        $companyEmail = SystemSetting::get('support_email', '');
        $vatNumber    = SystemSetting::get('vat_number', '');
        $companyReg   = SystemSetting::get('company_registration', '');

        // Property-specific details (phone + bank)
        $property        = $invoice->property_id ? Property::find($invoice->property_id) : null;
        $companyPhone    = $property?->phone ?? '';
        $bankName        = $property?->bank_name ?? '';
        $bankAccount     = $property?->bank_account ?? '';
        $bankAccountName = $property?->bank_account_name ?? '';
        $swiftCode       = $property?->swift_code ?? '';

        // Property (address shown in header)
        $property = $invoice->property_id
            ? Property::find($invoice->property_id)
            : null;

        // Tenant phone — try via lease → tenant relationship
        $tenantPhone = '';
        if ($invoice->lease_id) {
            $lease = $invoice->lease ?? $invoice->load('lease')->lease;
            if ($lease) {
                $tenant = Tenant::find($lease->tenant_id ?? null);
                $tenantPhone = $tenant?->phone ?? '';
            }
        }
        if (!$tenantPhone) {
            // Fallback: look up by email
            if ($invoice->tenant_email) {
                $tenant = Tenant::where('email', $invoice->tenant_email)->first();
                $tenantPhone = $tenant?->phone ?? '';
            }
        }

        // Tenant unit reference
        $tenantUnit = $invoice->unit_ref ?? '';

        // VAT rate — from linked lease, fallback 0
        $vatRate = 0;
        if ($invoice->lease_id) {
            $lease   = $invoice->relationLoaded('lease') ? $invoice->lease : $invoice->load('lease')->lease;
            $vatRate = (float) ($lease?->vat_rate ?? 0);
        }

        $data = compact(
            'invoice', 'items', 'property',
            'companyName', 'companyEmail', 'companyPhone',
            'vatNumber', 'companyReg',
            'tenantUnit', 'tenantPhone',
            'bankName', 'bankAccount', 'bankAccountName', 'swiftCode',
            'vatRate',
        );

        $data['invoiceLabel'] = 'PROFORMA INVOICE';

        $pdf = Pdf::loadView('pdf.proforma-invoice', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
