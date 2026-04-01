<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Lease;
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
     * Post invoice accrual entry when invoice is issued.
     *
     * GL lines (example: $1,180 gross, 18% VAT, $50 service charge):
     *   Dr: 1100  Rent Receivable     1,180.00
     *     Cr: 4000  Rental Income       950.00   (net rent, excl VAT)
     *     Cr: 4100  Service Charge Inc   42.37   (net SC, excl VAT)
     *     Cr: 2200  VAT Payable         187.63   (VAT on rent + SC)
     *
     * VAT is treated as inclusive (back-calculated from gross amounts).
     * Service charge items are identified by item_type = 'service_charge' (falls back to description matching for legacy rows).
     * All amounts are converted to TZS base currency before posting.
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

        // Load items and split by type
        $items = $invoice->items()->get();
        $scItems   = $items->filter(fn($i) => ($i->item_type ?? null) === 'service_charge'
            || (($i->item_type ?? 'other') === 'other' && stripos($i->description ?? '', 'service charge') !== false));
        $rentItems = $items->reject(fn($i)  => ($i->item_type ?? null) === 'service_charge'
            || (($i->item_type ?? 'other') === 'other' && stripos($i->description ?? '', 'service charge') !== false));

        $scGross   = (float) $scItems->sum('total');
        $rentGross = (float) $rentItems->sum('total');
        $invoiceTotal = $scGross + $rentGross;

        if ($invoiceTotal <= 0) {
            return;
        }

        // VAT rate from the linked lease (inclusive — back-calculated from gross)
        $vatRate = (float) ($invoice->lease->vat_rate ?? 0);
        $vatOnSc   = $vatRate > 0 && $scGross   > 0 ? round($scGross   * ($vatRate / (100 + $vatRate)), 2) : 0.0;
        $vatOnRent = $vatRate > 0 && $rentGross > 0 ? round($rentGross * ($vatRate / (100 + $vatRate)), 2) : 0.0;
        $totalVat  = round($vatOnSc + $vatOnRent, 2);
        $netRent   = round($rentGross - $vatOnRent, 2);
        $netSc     = round($scGross   - $vatOnSc,   2);

        DB::transaction(function () use ($invoice, $invoiceTotal, $netRent, $netSc, $totalVat, $vatRate) {
            $reference = 'INV-' . $invoice->id;

            try {
                // Handle multi-currency conversion
                $currency     = $invoice->currency ?? 'TZS';
                $exchangeRate = 1.0;
                $fx = 1.0;

                if ($currency !== 'TZS') {
                    $rateDate = $invoice->issued_date ?? now();

                    $rate = ExchangeRate::getRate(
                        propertyId: null,
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
                    $fx = $rate;

                    $invoice->update([
                        'exchange_rate' => $exchangeRate,
                        'total_in_base' => round($invoiceTotal * $fx, 2),
                    ]);
                }

                // Convert all GL amounts to base currency
                $arTotal  = round($invoiceTotal * $fx, 2);
                $crRent   = round($netRent  * $fx, 2);
                $crSc     = round($netSc    * $fx, 2);
                $crVat    = round($totalVat * $fx, 2);

                // Build balanced GL lines (Dr:1100 = Cr:4000 + Cr:4100 + Cr:2200)
                $lines = [
                    ['account_code' => '1100', 'debit' => $arTotal, 'credit' => 0],
                ];

                if ($crRent > 0) {
                    $lines[] = ['account_code' => '4000', 'debit' => 0, 'credit' => $crRent];
                }
                if ($crSc > 0) {
                    $lines[] = ['account_code' => '4100', 'debit' => 0, 'credit' => $crSc];
                }
                if ($crVat > 0) {
                    $lines[] = ['account_code' => '2200', 'debit' => 0, 'credit' => $crVat];
                }

                // Floating-point safety: adjust last credit line to balance exactly
                $creditSum = array_sum(array_column(array_filter($lines, fn($l) => $l['credit'] > 0), 'credit'));
                $diff = round($arTotal - $creditSum, 2);
                if (abs($diff) > 0 && count($lines) > 1) {
                    for ($i = count($lines) - 1; $i >= 1; $i--) {
                        if ($lines[$i]['credit'] > 0) {
                            $lines[$i]['credit'] = round($lines[$i]['credit'] + $diff, 2);
                            break;
                        }
                    }
                }

                $journalEntry = $this->poster->post(
                    propertyId: $invoice->property_id,
                    entryDate: $invoice->issued_date ?? now(),
                    description: 'Invoice issued: ' . $invoice->invoice_number,
                    reference: $reference,
                    lines: $lines
                );

                AccountingEvent::logSuccess(
                    propertyId: $invoice->property_id,
                    eventType: 'invoice_issued',
                    entityType: 'Invoice',
                    entityId: $invoice->id,
                    reference: $reference,
                    description: 'Invoice ' . $invoice->invoice_number . ' accrual posted',
                    data: [
                        'invoice_id'      => $invoice->id,
                        'invoice_number'  => $invoice->invoice_number,
                        'amount'          => $invoiceTotal,
                        'currency'        => $currency,
                        'exchange_rate'   => $exchangeRate,
                        'amount_in_base'  => $arTotal,
                        'vat_rate'        => $vatRate,
                        'vat_amount'      => $totalVat,
                        'net_rent'        => $netRent,
                        'net_sc'          => $netSc,
                        'status'          => $invoice->status,
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
                        'invoice_id'     => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount'         => $invoiceTotal,
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
     * Post payment entry.
     *
     * Invoice-linked (no WHT):
     *   Dr: 1000  Cash at Bank        amount
     *     Cr: 1100  Rent Receivable     amount
     *
     * Invoice-linked with WHT (tenant withholds tax):
     *   Dr: 1000  Cash at Bank        amount × (1 − wht%)   (actual cash received)
     *   Dr: 1120  WHT Tax Credit      amount × wht%         (recoverable from TRA)
     *     Cr: 1100  Rent Receivable     amount               (clears full AR balance)
     *
     * Not linked (cash-basis fallback):
     *   Dr: 1000  Cash at Bank        amount
     *     Cr: 4000  Rental Income       amount
     *
     * All amounts are converted to TZS base currency before posting.
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
                // Determine credit account and WHT rate based on invoice link
                $creditAccountCode = '4000'; // Default: Revenue (cash-basis)
                $whtRate = 0.0;

                if ($payment->invoice_id) {
                    $invoice = Invoice::with('lease')->find($payment->invoice_id);
                    if ($invoice && $invoice->type === 'invoice') {
                        $creditAccountCode = '1100'; // Receivable (accrual-aware)
                        $whtRate = (float) ($invoice->lease->wht_rate ?? 0);
                    }
                }

                // Handle multi-currency conversion
                $currency     = $payment->currency ?? 'TZS';
                $amountToPost = (float) $payment->amount;
                $exchangeRate = 1.0;

                if ($currency !== 'TZS') {
                    $rateDate = $payment->paid_date ?? now();

                    $rate = ExchangeRate::getRate(
                        propertyId: null,
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

                    $payment->update([
                        'exchange_rate'  => $exchangeRate,
                        'amount_in_base' => $amountToPost,
                    ]);
                }

                // Build GL lines, splitting for WHT if applicable
                if ($whtRate > 0 && $creditAccountCode === '1100') {
                    $whtAmount  = round($amountToPost * ($whtRate / 100), 2);
                    $cashAmount = round($amountToPost - $whtAmount, 2);
                    $lines = [
                        ['account_code' => '1000', 'debit' => $cashAmount,    'credit' => 0],
                        ['account_code' => '1120', 'debit' => $whtAmount,     'credit' => 0],
                        ['account_code' => '1100', 'debit' => 0,              'credit' => $amountToPost],
                    ];
                } else {
                    $lines = [
                        ['account_code' => '1000',               'debit' => $amountToPost, 'credit' => 0],
                        ['account_code' => $creditAccountCode,   'debit' => 0,             'credit' => $amountToPost],
                    ];
                }

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
                        'payment_id'     => $payment->id,
                        'amount'         => $payment->amount,
                        'currency'       => $currency,
                        'exchange_rate'  => $exchangeRate,
                        'amount_in_base' => $amountToPost,
                        'invoice_id'     => $payment->invoice_id,
                        'credit_account' => $creditAccountCode,
                        'wht_rate'       => $whtRate,
                        'cash_basis'     => $payment->invoice_id ? false : true,
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
                        'amount'     => $payment->amount,
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
     * Post maintenance ticket expense when resolved.
     *
     * Transaction: Dr:5000 (Maintenance Expense) / Cr:2000 (Accounts Payable)
     *
     * Multi-currency: if the record has a non-TZS currency, the cost is converted
     * to TZS using the historical rate on the resolved date, and cost_in_base is stored.
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
                $currency     = strtoupper((string) ($record->currency ?? 'TZS'));
                $cost         = (float) $record->cost;
                $exchangeRate = 1.0;
                $costInBase   = $cost;

                if ($currency !== 'TZS') {
                    $rateDate = $record->resolved_date ?? now();

                    $rate = ExchangeRate::getRate(
                        propertyId: null,
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
                    $costInBase   = round($cost * $exchangeRate, 2);

                    $record->update(['cost_in_base' => $costInBase]);
                }

                $lines = [
                    ['account_code' => '5000', 'debit' => $costInBase, 'credit' => 0],
                    ['account_code' => '2000', 'debit' => 0,           'credit' => $costInBase],
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
                        'record_id'     => $record->id,
                        'amount'        => $cost,
                        'currency'      => $currency,
                        'exchange_rate' => $exchangeRate,
                        'amount_in_base'=> $costInBase,
                        'description'   => $record->description,
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
                        'amount'    => $record->cost,
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

    /**
     * Post management fee accrual for a given property and period.
     *
     * GL entry:
     *   Dr: 5500  Management Fee Expense   amount
     *     Cr: 2500  Management Fee Payable   amount
     *
     * Idempotent: reference MGMTFEE-{propertyId}-{period} ensures only one entry per period.
     *
     * @param int    $propertyId  Property this fee belongs to
     * @param float  $amount      Fee amount in TZS (base currency)
     * @param string $period      Period identifier e.g. "2026-03"
     * @param string $entryDate   ISO date string for the GL entry
     */
    public function postManagementFee(int $propertyId, float $amount, string $period, string $entryDate): \App\Models\JournalEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Management fee amount must be positive.');
        }

        $reference = 'MGMTFEE-' . $propertyId . '-' . $period;

        return DB::transaction(function () use ($propertyId, $amount, $period, $entryDate, $reference) {
            try {
                $lines = [
                    ['account_code' => '5500', 'debit' => $amount, 'credit' => 0],
                    ['account_code' => '2500', 'debit' => 0,       'credit' => $amount],
                ];

                $journalEntry = $this->poster->post(
                    propertyId: $propertyId,
                    entryDate: $entryDate,
                    description: "Management fee: {$period}",
                    reference: $reference,
                    lines: $lines
                );

                AccountingEvent::logSuccess(
                    propertyId: $propertyId,
                    eventType: 'management_fee_posted',
                    entityType: 'Property',
                    entityId: $propertyId,
                    reference: $reference,
                    description: "Management fee for {$period} posted",
                    data: [
                        'property_id' => $propertyId,
                        'amount'      => $amount,
                        'period'      => $period,
                        'entry_date'  => $entryDate,
                    ],
                    postedEntries: [$journalEntry->id],
                );

                return $journalEntry;
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $propertyId,
                    eventType: 'management_fee_posted',
                    entityType: 'Property',
                    entityId: $propertyId,
                    reference: $reference,
                    description: 'Management fee posting failed',
                    data: [
                        'property_id' => $propertyId,
                        'amount'      => $amount,
                        'period'      => $period,
                    ],
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            }
        });
    }

    /**
     * Post deposit refund GL entry when a lease is terminated.
     *
     * Only fires if the original DEP-{lease_id} entry was actually posted (i.e. the
     * lease was active and a deposit was received). For pending/draft leases that were
     * never activated the DEP entry never existed, so no refund is needed.
     *
     * GL entry:
     *   Dr: 2100  Deposits Payable   amount   (clears the liability)
     *     Cr: 1000  Cash at Bank       amount   (cash paid back to tenant)
     *
     * Reference: DEP-REF-{lease_id}  (idempotent)
     */
    public function postDepositRefund(Lease $lease): void
    {
        $deposit = (float) ($lease->deposit ?? 0);
        if ($deposit <= 0) {
            return;
        }

        // Only post refund if the original deposit entry exists (was ever posted).
        // We check for any DEP-{id} entry regardless of status — if it was voided
        // it means we already reversed it, so no refund entry is needed.
        $originalExists = JournalEntry::query()
            ->where('reference', 'DEP-' . $lease->id)
            ->where('property_id', $lease->property_id)
            ->exists();

        if (!$originalExists) {
            return;
        }

        $currency = strtoupper((string) ($lease->currency ?? 'TZS'));
        $depositTzs = $currency === 'TZS'
            ? $deposit
            : round($deposit * ExchangeRate::getRate(
                propertyId: null,
                fromCurrency: $currency,
                toCurrency: 'TZS',
                date: $lease->start_date,
            ), 2);

        $reference = 'DEP-REF-' . $lease->id;

        DB::transaction(function () use ($lease, $depositTzs, $reference) {
            try {
                $journalEntry = $this->poster->post(
                    propertyId: $lease->property_id,
                    entryDate: now()->toDateString(),
                    description: 'Security deposit refunded',
                    reference: $reference,
                    lines: [
                        ['account_code' => '2100', 'debit' => $depositTzs, 'credit' => 0],
                        ['account_code' => '1000', 'debit' => 0,           'credit' => $depositTzs],
                    ],
                    sourceType: 'lease',
                    sourceId: $lease->id,
                );

                AccountingEvent::logSuccess(
                    propertyId: $lease->property_id,
                    eventType: 'deposit_refunded',
                    entityType: 'Lease',
                    entityId: $lease->id,
                    reference: $reference,
                    description: 'Security deposit refund posted for lease #' . $lease->id,
                    data: [
                        'lease_id'   => $lease->id,
                        'deposit'    => $lease->deposit,
                        'currency'   => $lease->currency,
                        'amount_tzs' => $depositTzs,
                    ],
                    postedEntries: [$journalEntry->id],
                );
            } catch (\Exception $e) {
                AccountingEvent::logFailure(
                    propertyId: $lease->property_id,
                    eventType: 'deposit_refunded',
                    entityType: 'Lease',
                    entityId: $lease->id,
                    reference: $reference,
                    description: 'Deposit refund posting failed',
                    data: ['lease_id' => $lease->id],
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
