<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentReceiptService
{
    public function buildPaymentBreakdown(?Invoice $invoice, float $paymentAmount): array
    {
        if (!$invoice) {
            return [
                'rent' => 0.0,
                'service_charge' => 0.0,
                'electricity' => 0.0,
                'has_lease_related' => false,
                'is_electricity_only' => false,
            ];
        }

        $items = $invoice->items ?? collect();
        $invoiceTotal = max(0, (float) $items->sum('total'));
        if ($invoiceTotal <= 0 || $paymentAmount <= 0) {
            return [
                'rent' => 0.0,
                'service_charge' => 0.0,
                'electricity' => 0.0,
                'has_lease_related' => false,
                'is_electricity_only' => false,
            ];
        }

        $ratio = min(1, $paymentAmount / $invoiceTotal);
        $rentTotal = 0.0;
        $serviceChargeTotal = 0.0;
        $electricityTotal = 0.0;

        foreach ($items as $item) {
            $bucket = $this->classifyInvoiceItem($item->item_type ?? null, $item->description ?? null);
            $amount = round((float) ($item->total ?? 0) * $ratio, 2);
            if ($bucket === 'rent') {
                $rentTotal += $amount;
            } elseif ($bucket === 'service_charge') {
                $serviceChargeTotal += $amount;
            } else {
                $electricityTotal += $amount;
            }
        }

        $rentTotal = round($rentTotal, 2);
        $serviceChargeTotal = round($serviceChargeTotal, 2);
        $electricityTotal = round($electricityTotal, 2);
        $sum = round($rentTotal + $serviceChargeTotal + $electricityTotal, 2);
        $delta = round($paymentAmount - $sum, 2);
        if (abs($delta) > 0) {
            $electricityTotal = round($electricityTotal + $delta, 2);
        }

        $hasLeaseRelated = $rentTotal > 0 || $serviceChargeTotal > 0;
        $isElectricityOnly = !$hasLeaseRelated && $electricityTotal > 0;

        return [
            'rent' => $rentTotal,
            'service_charge' => $serviceChargeTotal,
            'electricity' => $electricityTotal,
            'has_lease_related' => $hasLeaseRelated,
            'is_electricity_only' => $isElectricityOnly,
        ];
    }

    public function assertReceiptEligibility(
        bool $issueReceipt,
        bool $whtConfirmed,
        bool $hasLeaseRelatedCharges,
        bool $isPartialPayment
    ): void {
        if (!$issueReceipt) {
            return;
        }

        if ($isPartialPayment) {
            throw ValidationException::withMessages([
                'issue_receipt' => 'Receipt cannot be issued for partial payments.',
            ]);
        }

        if ($hasLeaseRelatedCharges && !$whtConfirmed) {
            throw ValidationException::withMessages([
                'wht_confirmed' => 'WHT confirmation is required before issuing a receipt for rent or service charge payments.',
            ]);
        }
    }

    public function createReceiptForPayment(Payment $payment, ?Invoice $invoice = null): Receipt
    {
        $issuedAt = $payment->paid_date
            ? Carbon::parse($payment->paid_date)->toDateString()
            : now()->toDateString();

        return Receipt::create([
            'receipt_number' => $this->nextReceiptNumber(),
            'payment_id' => $payment->id,
            'invoice_id' => $invoice?->id ?: $payment->invoice_id,
            'tenant_id' => $payment->tenant_id,
            'property_id' => $payment->property_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'TZS',
            'issued_at' => $issuedAt,
            'notes' => $payment->wht_reference ?: null,
        ]);
    }

    private function classifyInvoiceItem(?string $itemType, ?string $description): string
    {
        $type = strtolower((string) ($itemType ?? ''));
        if ($type === 'rent' || $type === 'service_charge' || $type === 'electricity') {
            return $type;
        }

        $text = strtolower((string) ($description ?? ''));
        if (Str::contains($text, 'service charge')) {
            return 'service_charge';
        }
        if (Str::contains($text, 'rent')) {
            return 'rent';
        }
        if (Str::contains($text, 'electricity') || Str::contains($text, 'generator') || Str::contains($text, 'submeter')) {
            return 'electricity';
        }

        return 'electricity';
    }

    private function nextReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'RCPT-' . $year . '-';
        $latest = Receipt::query()
            ->where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('receipt_number');

        $sequence = 1;
        if (is_string($latest)) {
            $parts = explode('-', $latest);
            $last = (int) end($parts);
            $sequence = $last + 1;
        }

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
