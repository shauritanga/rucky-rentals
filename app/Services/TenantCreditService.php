<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A tenant's credit: money paid beyond an invoice. An overpayment is recorded as credit, a later invoice can use it
 * (as a "credit" payment on that invoice, so every balance and report stays right), or it can be refunded.
 */
class TenantCreditService
{
    /** Keep the invoice's single overpayment credit row equal to what it is overpaid by now (create, change or remove it). */
    public function syncOverpayment(Invoice $invoice): void
    {
        $tenantId = $this->tenantIdFor($invoice);
        if (!$tenantId) {
            return;
        }

        $gross = app(PaymentController::class)->invoiceGrossTotalInBase($invoice);
        $paid = $this->paidOn($invoice);
        $over = $gross > 0 ? round(max(0, $paid - $gross), 2) : 0.0;

        $row = TenantCredit::where('type', 'overpayment')->where('source_invoice_id', $invoice->id)->first();

        if ($over > 0.005) {
            if ($row) {
                $row->update(['amount' => $over, 'tenant_id' => $tenantId]);
            } else {
                TenantCredit::create([
                    'tenant_id' => $tenantId, 'property_id' => $invoice->property_id, 'amount' => $over,
                    'type' => 'overpayment', 'source_invoice_id' => $invoice->id,
                    'note' => 'Overpayment on ' . $invoice->invoice_number,
                ]);
            }
        } elseif ($row) {
            $row->delete();
        }
    }

    /** Put (part of) the tenant's credit against an invoice: a real payment row marked as credit, no cash, no receipt. */
    public function apply(Tenant $tenant, Invoice $invoice, ?float $amount, ?int $userId = null): Payment
    {
        $invoice->loadMissing(['items', 'lease']);

        if ($invoice->type !== 'proforma' || $invoice->approval_status !== 'approved' || $invoice->status === 'paid') {
            throw ValidationException::withMessages(['invoice_id' => 'Credit can only be applied to an approved proforma that is not fully paid.']);
        }
        if ((int) $this->tenantIdFor($invoice) !== (int) $tenant->id) {
            throw ValidationException::withMessages(['invoice_id' => 'This invoice belongs to another tenant.']);
        }

        $balance = $tenant->creditBalance();
        $outstanding = round(app(PaymentController::class)->invoiceGrossTotalInBase($invoice) - $this->paidOn($invoice), 2);
        $amount = round(min($amount ?? PHP_FLOAT_MAX, $balance, $outstanding), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => $balance <= 0 ? 'This tenant has no credit to apply.' : 'Nothing is owing on this invoice.']);
        }

        $unitId = $this->unitIdFor($invoice);
        if (!$unitId) {
            throw ValidationException::withMessages(['invoice_id' => 'The unit for this invoice could not be found.']);
        }
        $breakdown = app(PaymentReceiptService::class)->buildPaymentBreakdown($invoice, $amount);

        return DB::transaction(function () use ($tenant, $invoice, $amount, $unitId, $breakdown, $userId) {
            $payment = Payment::create([
                'property_id' => $invoice->property_id, 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'unit_id' => $unitId,
                'month' => $invoice->period ?: now()->format('M Y'), 'amount' => $amount, 'amount_in_base' => $amount, 'currency' => 'TZS',
                'exchange_rate' => 1, 'method' => 'Credit applied', 'source' => 'credit', 'status' => 'paid', 'paid_date' => now()->toDateString(),
                'notes' => 'Applied from the tenant\'s credit (overpayment)',
                'breakdown_rent' => $breakdown['rent'], 'breakdown_service_charge' => $breakdown['service_charge'], 'breakdown_electricity' => $breakdown['electricity'],
            ]);

            TenantCredit::create([
                'tenant_id' => $tenant->id, 'property_id' => $invoice->property_id, 'amount' => -$amount, 'type' => 'applied',
                'target_invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'created_by' => $userId,
                'note' => 'Applied to ' . $invoice->invoice_number,
            ]);

            app(PaymentController::class)->reconcileInvoiceStatus($invoice->id);

            return $payment;
        });
    }

    /** Return money to the tenant: the credit goes down, nothing is recorded as a payment. */
    public function refund(Tenant $tenant, float $amount, ?string $method, ?string $reference, ?string $note, ?int $userId = null): TenantCredit
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > $tenant->creditBalance() + 0.005) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount up to the credit this tenant has.']);
        }

        return TenantCredit::create([
            'tenant_id' => $tenant->id, 'property_id' => $tenant->property_id, 'amount' => -$amount, 'type' => 'refund',
            'method' => $method, 'reference' => $reference, 'note' => $note ?: 'Refunded to tenant', 'created_by' => $userId,
        ]);
    }

    /** What the invoice has received in TZS, credit payments included. */
    public function paidOn(Invoice $invoice): float
    {
        return (float) Payment::where('invoice_id', $invoice->id)->where('status', 'paid')
            ->sum(DB::raw('COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))'));
    }

    /** The unit an invoice is for: its lease's unit, a unit it has been paid for, or the unit named on it. */
    public function unitIdFor(Invoice $invoice): ?int
    {
        if ($invoice->lease_id) {
            $unitId = Lease::where('id', $invoice->lease_id)->value('unit_id');
            if ($unitId) {
                return (int) $unitId;
            }
        }

        $fromPayment = Payment::where('invoice_id', $invoice->id)->whereNotNull('unit_id')->value('unit_id');
        if ($fromPayment) {
            return (int) $fromPayment;
        }

        return \App\Models\Unit::where('property_id', $invoice->property_id)->where('unit_number', $invoice->unit_ref)->value('id');
    }

    public function tenantIdFor(Invoice $invoice): ?int
    {
        if ($invoice->lease_id) {
            $tenantId = Lease::where('id', $invoice->lease_id)->value('tenant_id');
            if ($tenantId) {
                return (int) $tenantId;
            }
        }

        $fromPayment = Payment::where('invoice_id', $invoice->id)->whereNotNull('tenant_id')->value('tenant_id');
        if ($fromPayment) {
            return (int) $fromPayment;
        }

        return Tenant::where('property_id', $invoice->property_id)->where('name', $invoice->tenant_name)->value('id');
    }
}
