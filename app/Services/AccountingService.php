<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\MaintenanceRecord;
use App\Models\ExchangeRate;
use App\Support\AccountingAutoPoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Professional accounting service layer
 * 
 * This service:
 * - Centralizes all accounting business logic
 * - Ensures atomicity of transactions
 * - Maintains immutable event sourcing audit trail
 * - Handles idempotent references for safe retry
 * - Provides comprehensive error handling
 */
class AccountingService
{
    public function __construct(private AccountingAutoPoster $poster) {}

    /**
     * Post invoice accrual entry when invoice is issued
     * 
     * Transaction: Dr:1100 (Rent Receivable) / Cr:4000 (Rental Income)
     * 
     * Multi-currency support:
     * - Invoices in USD are converted to TZS (base currency) using exchange rates
     * - exchange_rate and total_in_base are stored for audit trail
     * - All GL entries posted in base currency (TZS)
     */
    public function postInvoice(Invoice $invoice): void
    {
        // Skip drafts and proformas (not yet issued)
        if ($invoice->status === 'draft' || $invoice->status === 'proforma') {
            return;
        }

        // Skip non-invoice types
        if ($invoice->type !== 'invoice') {
            return;
        }

        // Calculate total from invoice items (invoices table doesn't have a total column)
        $invoiceTotal = $invoice->items()->sum('total');

        if ($invoiceTotal <= 0) {
            return;
        }

        DB::transaction(function () use ($invoice, $invoiceTotal) {
            $reference = 'INV-' . $invoice->id;

            try {
                // Handle multi-currency conversion
                $currency = $invoice->currency ?? 'TZS';
                $amountToPost = (float) $invoiceTotal;
                $exchangeRate = 1.0;

                if ($currency !== 'TZS') {
                    $rateDate = $invoice->issued_date ?? now();

                    // Look up exchange rate for USD -> TZS
                    $rate = ExchangeRate::getRate(
                        propertyId: $invoice->property_id,
                        fromCurrency: $currency,
                        toCurrency: 'TZS',
                        date: $rateDate
                    );

                    if ($rate === null) {
                        throw new \Exception(
                            "Exchange rate not found for {$currency} to TZS on " .
                                Carbon::parse($rateDate)->toDateString()
                        );
                    }

                    $exchangeRate = $rate;
                    $amountToPost = (float) $invoiceTotal * $exchangeRate;

                    // Store conversion info on invoice for audit
                    $invoice->update([
                        'exchange_rate' => $exchangeRate,
                        'total_in_base' => $amountToPost,
                    ]);
                }

                $lines = [
                    [
                        'account_code' => '1100',
                        'debit' => $amountToPost,
                        'credit' => 0,
                    ],
                    [
                        'account_code' => '4000',
                        'debit' => 0,
                        'credit' => $amountToPost,
                    ],
                ];

                $journalEntry = $this->poster->post(
                    propertyId: $invoice->property_id,
                    entryDate: $invoice->issued_date ?? now(),
                    description: 'Invoice issued: ' . $invoice->invoice_number,
                    reference: $reference,
                    lines: $lines
                );

                // Log successful posting
                AccountingEvent::logSuccess(
                    propertyId: $invoice->property_id,
                    eventType: 'invoice_issued',
                    entityType: 'Invoice',
                    entityId: $invoice->id,
                    reference: $reference,
                    description: 'Invoice ' . $invoice->invoice_number . ' accrual posted',
                    data: [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoiceTotal,
                        'currency' => $currency,
                        'exchange_rate' => $exchangeRate,
                        'amount_in_base' => $amountToPost,
                        'status' => $invoice->status,
                    ],
                    postedEntries: [$journalEntry->id],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $invoice->property_id,
                    eventType: 'invoice_issued',
                    entityType: 'Invoice',
                    entityId: $invoice->id,
                    reference: $reference,
                    description: 'Invoice accrual posting failed',
                    data: [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoiceTotal,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Void invoice accrual entry when invoice is cancelled/drafted
     */
    public function voidInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $reference = 'INV-' . $invoice->id;

            try {
                $this->poster->voidByReference($invoice->property_id, $reference);

                AccountingEvent::logReversal(
                    propertyId: $invoice->property_id,
                    eventType: 'invoice_voided',
                    entityType: 'Invoice',
                    entityId: $invoice->id,
                    reference: $reference,
                    description: 'Invoice ' . $invoice->invoice_number . ' accrual reversed',
                    data: [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'previous_status' => $invoice->status,
                    ],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $invoice->property_id,
                    eventType: 'invoice_voided',
                    entityType: 'Invoice',
                    entityId: $invoice->id,
                    reference: $reference,
                    description: 'Invoice accrual void failed',
                    data: [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Post payment entry
     * 
     * If invoice-linked: Dr:1000 (Cash) / Cr:1100 (Rent Receivable)
     * If not linked: Dr:1000 (Cash) / Cr:4000 (Rental Income) — cash-basis fallback
     * 
     * Multi-currency support:
     * - Payments in USD are converted to TZS using exchange rates
     * - exchange_rate and amount_in_base are stored for audit
     * - GL entries posted in base currency
     */
    public function postPayment(Payment $payment): void
    {
        // Only post if paid
        if ($payment->status !== 'paid') {
            return;
        }

        DB::transaction(function () use ($payment) {
            $reference = 'PAY-' . $payment->id;

            try {
                // Determine credit account based on invoice link
                $creditAccountCode = '4000'; // Default: Revenue (cash-basis)

                if ($payment->invoice_id) {
                    $invoice = Invoice::find($payment->invoice_id);
                    if ($invoice && $invoice->type === 'invoice') {
                        $creditAccountCode = '1100'; // Receivable (accrual-aware)
                    }
                }

                // Handle multi-currency conversion
                $currency = $payment->currency ?? 'TZS';
                $amountToPost = (float) $payment->amount;
                $exchangeRate = 1.0;

                if ($currency !== 'TZS') {
                    $rateDate = $payment->paid_date ?? now();

                    // Look up exchange rate for USD -> TZS
                    $rate = ExchangeRate::getRate(
                        propertyId: $payment->property_id,
                        fromCurrency: $currency,
                        toCurrency: 'TZS',
                        date: $rateDate
                    );

                    if ($rate === null) {
                        throw new \Exception(
                            "Exchange rate not found for {$currency} to TZS on " .
                                Carbon::parse($rateDate)->toDateString()
                        );
                    }

                    $exchangeRate = $rate;
                    $amountToPost = (float) $payment->amount * $exchangeRate;

                    // Store conversion info on payment for audit
                    $payment->update([
                        'exchange_rate' => $exchangeRate,
                        'amount_in_base' => $amountToPost,
                    ]);
                }

                $lines = [
                    [
                        'account_code' => '1000',
                        'debit' => $amountToPost,
                        'credit' => 0,
                    ],
                    [
                        'account_code' => $creditAccountCode,
                        'debit' => 0,
                        'credit' => $amountToPost,
                    ],
                ];

                $journalEntry = $this->poster->post(
                    propertyId: $payment->property_id,
                    entryDate: $payment->paid_date ?? now(),
                    description: 'Payment received: ' . ($payment->invoice_id ? 'INV-' . $payment->invoice_id : 'General'),
                    reference: $reference,
                    lines: $lines
                );

                AccountingEvent::logSuccess(
                    propertyId: $payment->property_id,
                    eventType: 'payment_posted',
                    entityType: 'Payment',
                    entityId: $payment->id,
                    reference: $reference,
                    description: 'Payment posted',
                    data: [
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $currency,
                        'exchange_rate' => $exchangeRate,
                        'amount_in_base' => $amountToPost,
                        'invoice_id' => $payment->invoice_id,
                        'credit_account' => $creditAccountCode,
                    ],
                    postedEntries: [$journalEntry->id],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $payment->property_id,
                    eventType: 'payment_posted',
                    entityType: 'Payment',
                    entityId: $payment->id,
                    reference: $reference,
                    description: 'Payment posting failed',
                    data: [
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Void payment entry when payment is cancelled/reverted
     */
    public function voidPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $reference = 'PAY-' . $payment->id;

            try {
                $this->poster->voidByReference($payment->property_id, $reference);

                AccountingEvent::logReversal(
                    propertyId: $payment->property_id,
                    eventType: 'payment_voided',
                    entityType: 'Payment',
                    entityId: $payment->id,
                    reference: $reference,
                    description: 'Payment reversed',
                    data: [
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                    ],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $payment->property_id,
                    eventType: 'payment_voided',
                    entityType: 'Payment',
                    entityId: $payment->id,
                    reference: $reference,
                    description: 'Payment void failed',
                    data: [
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Post maintenance ticket expense when resolved
     * 
     * Transaction: Dr:5000 (Maintenance Expense) / Cr:2000 (Accounts Payable)
     */
    public function postMaintenanceRecord(MaintenanceRecord $record): void
    {
        // Only post if resolved and has cost
        if ($record->status !== 'resolved' || $record->cost <= 0) {
            return;
        }

        DB::transaction(function () use ($record) {
            $reference = 'MAINT-' . $record->id;

            try {
                $lines = [
                    [
                        'account_code' => '5000',
                        'debit' => (float) $record->cost,
                        'credit' => 0,
                    ],
                    [
                        'account_code' => '2000',
                        'debit' => 0,
                        'credit' => (float) $record->cost,
                    ],
                ];

                $journalEntry = $this->poster->post(
                    propertyId: $record->property_id,
                    entryDate: $record->resolved_date ?? now(),
                    description: 'Maintenance: ' . $record->description,
                    reference: $reference,
                    lines: $lines
                );

                AccountingEvent::logSuccess(
                    propertyId: $record->property_id,
                    eventType: 'maintenance_posted',
                    entityType: 'MaintenanceRecord',
                    entityId: $record->id,
                    reference: $reference,
                    description: 'Maintenance expense posted',
                    data: [
                        'record_id' => $record->id,
                        'amount' => $record->cost,
                        'description' => $record->description,
                    ],
                    postedEntries: [$journalEntry->id],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $record->property_id,
                    eventType: 'maintenance_posted',
                    entityType: 'MaintenanceRecord',
                    entityId: $record->id,
                    reference: $reference,
                    description: 'Maintenance posting failed',
                    data: [
                        'record_id' => $record->id,
                        'amount' => $record->cost,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Void maintenance ticket entry
     */
    public function voidMaintenanceRecord(MaintenanceRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $reference = 'MAINT-' . $record->id;

            try {
                $this->poster->voidByReference($record->property_id, $reference);

                AccountingEvent::logReversal(
                    propertyId: $record->property_id,
                    eventType: 'maintenance_voided',
                    entityType: 'MaintenanceRecord',
                    entityId: $record->id,
                    reference: $reference,
                    description: 'Maintenance expense reversed',
                    data: [
                        'record_id' => $record->id,
                        'amount' => $record->cost,
                    ],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $record->property_id,
                    eventType: 'maintenance_voided',
                    entityType: 'MaintenanceRecord',
                    entityId: $record->id,
                    reference: $reference,
                    description: 'Maintenance void failed',
                    data: [
                        'record_id' => $record->id,
                        'amount' => $record->cost,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    // Backward compatibility for older callers
    public function postMaintenanceTicket(MaintenanceRecord $record): void
    {
        $this->postMaintenanceRecord($record);
    }

    // Backward compatibility for older callers
    public function voidMaintenanceTicket(MaintenanceRecord $record): void
    {
        $this->voidMaintenanceRecord($record);
    }

    /**
     * Get complete audit trail for an entity reference (INV-X, PAY-Y, etc.)
     */
    public function getAuditTrail(string $reference)
    {
        return AccountingEvent::auditTrail($reference);
    }

    /**
     * Get all accounting events for a property in a date range
     */
    public function getEventsByDateRange(int $propertyId, string $startDate, string $endDate)
    {
        return AccountingEvent::where('property_id', $propertyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
