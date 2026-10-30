<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\LeaseInstallment;
use App\Models\Payment;
use App\Support\ComputesPaymentRevenue;
use App\Support\FloorConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The "Floor Tenant Schedule": one row per lease installment, grouped by floor, with
 * rent, service charge, VAT, WHT, what was paid and what is still owed. All figures are TZS.
 */
class FloorScheduleReport
{
    use ComputesPaymentRevenue;

    public const MONEY = [
        'rent_per_month', 'sc_per_month', 'total_rent', 'total_sc', 'excl', 'vat', 'incl',
        'wht_rent', 'wht_sc', 'wht_total', 'net_after_wht', 'wht_paid', 'paid', 'balance',
    ];

    /**
     * @return array{sections: array<int, array>, grand: array, from: string, to: string}
     */
    public function build(?int $propertyId, ?string $floor, Carbon $from, Carbon $to): array
    {
        $installments = LeaseInstallment::query()
            ->with(['lease.tenant', 'lease.unit', 'invoice.items', 'invoice.lease', 'invoice.payments.receipt', 'invoice.payments.invoice.lease'])
            ->whereHas('lease', function ($q) use ($propertyId) {
                $q->whereIn('status', ['active', 'expiring', 'overdue'])
                    ->when($propertyId !== null, fn ($q) => $q->where('property_id', $propertyId));
            })
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->get()
            ->filter(fn (LeaseInstallment $i) => $i->lease?->unit !== null)
            ->when($floor !== null && $floor !== '', fn (Collection $c) => $c->filter(fn (LeaseInstallment $i) => (string) $i->lease->unit->floor === (string) $floor));

        $sections = $installments
            ->groupBy(fn (LeaseInstallment $i) => (string) $i->lease->unit->floor)
            ->sortBy(fn ($rows, $floorCode) => FloorConfig::sortOrder((string) $floorCode))
            ->map(function (Collection $floorRows, $floorCode) {
                $rows = $this->rowsForFloor($floorRows);

                return [
                    'floor' => (string) $floorCode,
                    'label' => $this->floorLabel((string) $floorCode),
                    'rows' => $rows,
                    'totals' => $this->totals($rows),
                ];
            })
            ->values()
            ->all();

        $allRows = collect($sections)->flatMap(fn ($s) => $s['rows']);

        return [
            'sections' => $sections,
            'grand' => $this->totals($allRows->all()),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    /** Floors that have at least one billed lease, for the filter dropdown. */
    public function floorOptions(?int $propertyId): array
    {
        return \App\Models\Unit::query()
            ->when($propertyId !== null, fn ($q) => $q->where('property_id', $propertyId))
            ->whereHas('leases', fn ($q) => $q->whereIn('status', ['active', 'expiring', 'overdue']))
            ->distinct()
            ->pluck('floor')
            ->map(fn ($f) => (string) $f)
            ->sortBy(fn ($f) => FloorConfig::sortOrder($f))
            ->values()
            ->map(fn ($f) => ['id' => $f, 'label' => $this->floorLabel($f)])
            ->all();
    }

    private function rowsForFloor(Collection $installments): array
    {
        $sorted = $installments->sortBy([
            fn ($a, $b) => strcmp((string) $a->lease->tenant?->name, (string) $b->lease->tenant?->name),
            fn ($a, $b) => (int) $a->lease_id <=> (int) $b->lease_id,
            fn ($a, $b) => strcmp((string) $a->period_start, (string) $b->period_start),
        ]);

        $rows = [];
        $sn = 0;
        $lastLeaseId = null;

        foreach ($sorted as $installment) {
            $first = $installment->lease_id !== $lastLeaseId;
            if ($first) {
                $sn++;
            }
            $lastLeaseId = $installment->lease_id;

            $rows[] = ['sn' => $sn, 'first_of_lease' => $first] + $this->rowFor($installment);
        }

        return $rows;
    }

    private function rowFor(LeaseInstallment $installment): array
    {
        $lease = $installment->lease;
        $invoice = $installment->invoice;

        $start = Carbon::parse($installment->period_start);
        $end = Carbon::parse($installment->period_end);
        $months = max(1, (int) round($start->diffInMonths($end->copy()->addDay(), true)));

        // Rent and service charge for the whole period, excluding VAT.
        $rate = $this->tzsRate($installment, $invoice);
        if ($invoice && $invoice->items->isNotEmpty()) {
            $rent = (float) $invoice->items->where('item_type', 'rent')->sum('total');
            $sc = (float) $invoice->items->where('item_type', 'service_charge')->sum('total');
        } else {
            $monthlyRent = (float) $lease->monthly_rent;
            $monthlySc = (float) $lease->monthly_service_charge;
            $monthlyTotal = $monthlyRent + $monthlySc;
            $amount = (float) $installment->amount * $rate;
            $rent = $monthlyTotal > 0 ? $amount * ($monthlyRent / $monthlyTotal) : $amount;
            $sc = $amount - $rent;
        }

        $vatRate = (float) ($lease->vat_rate ?? 18);
        $whtRentRate = (float) ($lease->wht_rate ?? 10);
        $whtScRate = (float) ($lease->service_charge_rate ?? 5);

        $excl = $rent + $sc;
        $vat = $excl * $vatRate / 100;
        $incl = $excl + $vat;
        $whtRent = $rent * $whtRentRate / 100;
        $whtSc = $sc * $whtScRate / 100;
        $whtTotal = $whtRent + $whtSc;

        $payments = $invoice ? $invoice->payments->where('status', 'paid') : collect();
        $paid = (float) $payments->sum(fn (Payment $p) => $this->paymentBaseAmount($p));
        $whtPaid = (float) $payments->filter(fn (Payment $p) => $p->wht_confirmed)
            ->sum(fn (Payment $p) => $this->calculatePaymentWhtTotal($p));
        $efd = $payments->map(fn (Payment $p) => $p->receipt?->receipt_number)->filter()->unique()->implode(', ');

        return [
            'installment_id' => $installment->id,
            'tenant' => $lease->tenant?->name ?? '—',
            'unit' => $lease->unit?->unit_number,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'period' => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
            'terms' => $months,
            'rent_per_month' => round($rent / $months, 2),
            'sc_per_month' => round($sc / $months, 2),
            'total_rent' => round($rent, 2),
            'total_sc' => round($sc, 2),
            'excl' => round($excl, 2),
            'vat' => round($vat, 2),
            'incl' => round($incl, 2),
            'wht_rent' => round($whtRent, 2),
            'wht_sc' => round($whtSc, 2),
            'wht_total' => round($whtTotal, 2),
            'net_after_wht' => round($incl - $whtTotal, 2),
            'wht_paid' => round($whtPaid, 2),
            'paid' => round($paid, 2),
            'balance' => round(max(0, $incl - $paid), 2),
            'efd' => $efd,
            'invoice_number' => $invoice?->invoice_number,
            'remark' => $installment->remark,
        ];
    }

    /** USD installments are reported in TZS using the rate on their invoice, else the lease's rate. */
    private function tzsRate(LeaseInstallment $installment, ?Invoice $invoice): float
    {
        if (strtoupper((string) $installment->currency) !== 'USD') {
            return 1.0;
        }

        return (float) ($invoice?->usd_rate ?: $installment->lease?->display_rate ?: 0);
    }

    private function totals(array $rows): array
    {
        $totals = [];
        foreach (self::MONEY as $key) {
            $totals[$key] = round(array_sum(array_column($rows, $key)), 2);
        }

        return $totals;
    }

    private function floorLabel(string $code): string
    {
        return match (true) {
            $code === 'G' => 'Ground Floor',
            $code === 'P' => 'Parking Floor',
            $code === 'M' => 'Mezzanine',
            str_starts_with($code, 'B') => 'Basement ' . substr($code, 1),
            is_numeric($code) => $this->ordinal((int) $code) . ' Floor',
            default => $code === '' ? 'No floor' : $code,
        };
    }

    private function ordinal(int $n): string
    {
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : match ($n % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };

        return $n . $suffix;
    }
}
