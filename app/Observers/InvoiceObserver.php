<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AccountingService;

class InvoiceObserver
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Handle the Invoice "created" event.
     * Post accrual entry if invoice is in issued state
     */
    public function created(Invoice $invoice): void
    {
        $this->accountingService->postInvoice($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     * Handle status transitions: void if moving to draft, post if moving to issued
     */
    public function updated(Invoice $invoice): void
    {
        $original = $invoice->getOriginal();

        // If transitioning TO draft/proforma, void the entry
        if (
            in_array($invoice->status, ['draft', 'proforma']) &&
            !in_array($original['status'], ['draft', 'proforma'])
        ) {
            $this->accountingService->voidInvoice($invoice);
            return;
        }

        // If transitioning FROM draft to issued, post the entry
        if (
            in_array($original['status'], ['draft', 'proforma']) &&
            !in_array($invoice->status, ['draft', 'proforma'])
        ) {
            $this->accountingService->postInvoice($invoice);
            return;
        }
    }

    /**
     * Handle the Invoice "deleting" event.
     * Void the accrual entry before soft delete
     */
    public function deleting(Invoice $invoice): void
    {
        // Only void if it was posted (not draft)
        if ($invoice->status !== 'draft' && $invoice->status !== 'proforma') {
            $this->accountingService->voidInvoice($invoice);
        }
    }
}
