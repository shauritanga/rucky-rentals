<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositInvoiceService
{
    public function __construct(private InvoiceNumberService $numbers) {}

    /**
     * Issue the refundable security-deposit proforma for a lease: TZS, no VAT, no WHT,
     * never converted to a tax invoice. Returns the existing one if already issued.
     */
    public function issueFor(Lease $lease, ?int $userId = null): ?Invoice
    {
        $deposit = (float) ($lease->deposit ?? 0);
        if ($deposit <= 0) {
            return null;
        }

        $existing = Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->first();
        if ($existing) {
            return $existing;
        }

        $lease->loadMissing(['tenant', 'unit']);

        $isUsd = strtoupper((string) ($lease->currency ?? 'TZS')) === 'USD';
        $rate  = $isUsd ? (float) $lease->exchange_rate : null;
        if ($isUsd && $rate <= 0) {
            throw ValidationException::withMessages([
                'deposit_rate' => 'Enter the current USD → TZS rate to issue this lease\'s security deposit proforma.',
            ]);
        }
        $amount = $isUsd ? round($deposit * $rate, 2) : round($deposit, 2);
        $unitRef = $lease->unit?->unit_number ?? '-';

        return DB::transaction(function () use ($lease, $amount, $rate, $unitRef, $userId) {
            $invoice = Invoice::create([
                'invoice_number'       => $this->numbers->generateForDeposit($lease->tenant, null, now()->toDateString()),
                'type'                 => 'proforma',
                'kind'                 => 'deposit',
                'property_id'          => $lease->property_id,
                'requested_by_user_id' => $userId,
                'lease_id'             => $lease->id,
                'tenant_name'          => $lease->tenant?->name ?? 'Tenant',
                'tenant_email'         => $lease->tenant?->email,
                'unit_ref'             => $unitRef,
                'issued_date'          => now()->toDateString(),
                'due_date'             => $lease->start_date,
                'period'               => null,
                'status'               => 'proforma',
                'approval_status'      => 'approved',
                'approval_decided_at'  => now(),
                'approval_decided_by'  => $userId,
                'notes'                => 'Refundable security deposit. Not subject to VAT; a payment receipt is issued on payment.',
                'currency'             => 'TZS',
                'exchange_rate'        => 1.0,
                'usd_rate'             => $rate,
                'total_in_base'        => $amount,
            ]);

            InvoiceItem::create([
                'invoice_id'      => $invoice->id,
                'description'     => 'Security Deposit - Unit ' . $unitRef,
                'item_type'       => 'security_deposit',
                'sub_description' => 'Lease ' . ($lease->lease_number ?? $lease->id) . ' (refundable)',
                'quantity'        => 1,
                'unit_price'      => $amount,
                'total'           => $amount,
            ]);

            return $invoice;
        });
    }
}
