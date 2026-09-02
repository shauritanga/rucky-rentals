<?php

namespace App\Mail;

use App\Models\Invoice;
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
            subject: 'Proforma Invoice ' . $this->invoice->invoice_number . ' — Mwamba Properties',
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
        $invoice = $this->invoice;
        $items   = $this->items;

        // Company/property/tenant/unit details (mirrors InvoiceController::downloadPdf)
        $viewData = $invoice->buildPdfViewData($items);
        unset($viewData['tenantId']);

        $data = array_merge($viewData, compact('invoice', 'items'));
        $data['invoiceLabel'] = 'PROFORMA INVOICE';

        $pdf = Pdf::loadView('pdf.proforma-invoice', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
