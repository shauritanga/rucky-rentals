<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;

/**
 * How a paid payment splits into net rent, service charge, electricity, VAT and WHT.
 * Shared by the Reports page and the superuser owner-revenue waterfall.
 */
trait ComputesPaymentRevenue
{
    private function calculatePaymentWhtTotal(Payment $payment): float
    {
        $rentRate = (float) ($payment->invoice?->lease?->wht_rate ?? 0);
        $serviceChargeRate = (float) ($payment->invoice?->lease?->service_charge_rate ?? 5);
        $rentBase = (float) $payment->breakdown_rent;
        $serviceChargeBase = (float) $payment->breakdown_service_charge;

        if ($rentBase <= 0 && $serviceChargeBase <= 0) {
            return 0.0;
        }

        $fx = ($payment->amount > 0 && !empty($payment->amount_in_base))
            ? (float) $payment->amount_in_base / (float) $payment->amount
            : (float) ($payment->exchange_rate ?? 1);

        return round(
            (($rentBase * $fx) * ($rentRate / 100)) +
            (($serviceChargeBase * $fx) * ($serviceChargeRate / 100)),
            2
        );
    }

    private function paymentNetRevenueSummary(Payment $payment): array
    {
        $baseAmount = $this->paymentBaseAmount($payment);
        $fx = $this->paymentFx($payment);

        if ($payment->invoice) {
            $invoiceSummary = $this->invoiceNetRevenueSummary($payment->invoice);
            $items = $payment->invoice->items ?? collect();
            $leaseVatBase = $this->leaseVatAmountForInvoice($payment->invoice) * $fx;
            $electricityVatBase = (float) $items
                ->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat')
                ->sum('total') * $fx;

            $totalDueBase = $invoiceSummary['value'] + $leaseVatBase + $electricityVatBase;
            $ratio = $totalDueBase > 0 ? min(1, $baseAmount / $totalDueBase) : 0.0;

            $rent = round($invoiceSummary['rent'] * $ratio, 2);
            $serviceCharge = round($invoiceSummary['service_charge'] * $ratio, 2);
            $electricity = round($invoiceSummary['electricity'] * $ratio, 2);

            return [
                'rent' => $rent,
                'service_charge' => $serviceCharge,
                'electricity' => $electricity,
                'value' => round($rent + $serviceCharge + $electricity, 2),
            ];
        }

        $vatRate = $this->cashBasisVatRateForPayment($payment);
        $netAmount = $vatRate > 0
            ? round($baseAmount / (1 + ($vatRate / 100)), 2)
            : round($baseAmount, 2);

        return [
            'rent' => $netAmount,
            'service_charge' => 0.0,
            'electricity' => 0.0,
            'value' => $netAmount,
        ];
    }

    private function invoiceNetRevenueSummary(Invoice $invoice): array
    {
        $fx = $this->invoiceFx($invoice);
        $items = $invoice->items ?? collect();

        $electricityChargeItems = $items->filter(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity'], true)
        );
        $electricityVatItems = $items->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat');
        $nonElectricityItems = $items->reject(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity', 'electricity_vat'], true)
        );

        $serviceChargeItems = $nonElectricityItems->filter(
            fn ($item) => ($item->item_type ?? null) === 'service_charge'
                || (($item->item_type ?? 'other') === 'other' && stripos($item->description ?? '', 'service charge') !== false)
        );

        $rentItems = $nonElectricityItems->reject(
            fn ($item) => ($item->item_type ?? null) === 'service_charge'
                || (($item->item_type ?? 'other') === 'other' && stripos($item->description ?? '', 'service charge') !== false)
        );

        $rent = round((float) $rentItems->sum('total') * $fx, 2);
        $serviceCharge = round((float) $serviceChargeItems->sum('total') * $fx, 2);
        $electricityCharge = round((float) $electricityChargeItems->sum('total') * $fx, 2);
        $electricity = $electricityVatItems->isNotEmpty()
            ? $electricityCharge
            : round($this->normalizeLegacyElectricityNetAmount($electricityChargeItems, $electricityCharge), 2);

        return [
            'rent' => $rent,
            'service_charge' => $serviceCharge,
            'electricity' => $electricity,
            'value' => round($rent + $serviceCharge + $electricity, 2),
        ];
    }

    private function paymentNetElectricityBase(Payment $payment, float $fx): float
    {
        $gross = round((float) $payment->breakdown_electricity * $fx, 2);
        if ($gross <= 0) {
            return 0.0;
        }

        $items = $payment->invoice?->items ?? collect();
        $electricityChargeItems = $items->filter(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity'], true)
        );
        $electricityVat = (float) $items
            ->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat')
            ->sum('total');

        if ($electricityVat > 0) {
            $electricityCharge = (float) $electricityChargeItems->sum('total');
            $electricityGross = $electricityCharge + $electricityVat;

            if ($electricityGross > 0) {
                return round($gross * ($electricityCharge / $electricityGross), 2);
            }
        }

        return round($this->normalizeLegacyElectricityNetAmount($electricityChargeItems, $gross), 2);
    }

    private function normalizeLegacyElectricityNetAmount($electricityChargeItems, float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        $hasLegacyGrossSubmeterLine = $electricityChargeItems->contains(
            fn ($item) => stripos((string) ($item->description ?? ''), 'Electricity — Submeter Sale') !== false
        );

        if ($hasLegacyGrossSubmeterLine) {
            return round($gross / 1.18, 2);
        }

        return round($gross, 2);
    }

    private function paymentBaseAmount(Payment $payment): float
    {
        if ($payment->amount_in_base !== null) {
            return (float) $payment->amount_in_base;
        }

        return round((float) $payment->amount * (float) ($payment->exchange_rate ?? 1), 2);
    }

    private function paymentFx(Payment $payment): float
    {
        $amount = (float) $payment->amount;
        if ($amount > 0 && $payment->amount_in_base !== null) {
            return (float) $payment->amount_in_base / $amount;
        }

        return (float) ($payment->exchange_rate ?? 1);
    }

    private function invoiceFx(Invoice $invoice): float
    {
        return (float) ($invoice->exchange_rate ?? 1);
    }

    private function leaseVatAmountForInvoice(Invoice $invoice): float
    {
        $vatRate = (float) ($invoice->lease?->vat_rate ?? 0);
        if ($vatRate <= 0) {
            return 0.0;
        }

        $items = $invoice->items ?? collect();
        $leaseNet = (float) $items
            ->reject(fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity', 'electricity_vat'], true))
            ->sum('total');

        return round($leaseNet * ($vatRate / 100), 2);
    }

    private function cashBasisVatRateForPayment(Payment $payment): float
    {
        if (!empty($payment->invoice?->lease?->vat_rate)) {
            return (float) $payment->invoice->lease->vat_rate;
        }

        if ($payment->unit_id) {
            return (float) (Lease::query()
                ->where('unit_id', $payment->unit_id)
                ->whereIn('status', ['active', 'expiring', 'overdue'])
                ->value('vat_rate') ?? 18);
        }

        return 18.0;
    }
}
