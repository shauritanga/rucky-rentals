<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Unit;
use Carbon\Carbon;

class LeaseShareRebalancer
{
    /**
     * Re-split a unit's rent and service charge equally across its running leases.
     * Only installments that are not invoiced, unpaid and not yet started are repriced,
     * so anything already billed or paid keeps its amount.
     *
     * @return array{updated: string[], skipped: string[], proformas: string[]}
     */
    public function rebalance(Unit $unit, ?Lease $activating = null): array
    {
        $result = ['updated' => [], 'skipped' => [], 'proformas' => []];

        $leases = $unit->leases()->whereIn('status', ['active', 'expiring', 'overdue'])->get();
        $n = $leases->count();
        if ($n === 0) {
            return $result;
        }

        $targetRent = round((float) $unit->rent / $n, 2);
        $targetSc   = round((float) $unit->service_charge / $n, 2);

        foreach ($leases as $lease) {
            $isActivating = $activating && $lease->id === $activating->id;

            if (!$isActivating && !$this->isEvenShare($lease, $unit, $n)) {
                $result['skipped'][] = $lease->lease_number;
                continue;
            }

            $currentSc = $lease->service_charge ?? (float) $unit->service_charge;
            if (abs((float) $lease->monthly_rent - $targetRent) < 0.01 && abs($currentSc - $targetSc) < 0.01) {
                continue;
            }

            $lease->update(['monthly_rent' => $targetRent, 'service_charge' => $targetSc]);
            $this->repriceFutureInstallments($lease, $targetRent + $targetSc);
            $result['updated'][] = $lease->lease_number;

            $proformas = Invoice::where('lease_id', $lease->id)
                ->where('type', 'proforma')
                ->whereDate('due_date', '>=', Carbon::today())
                ->whereDoesntHave('payments')
                ->pluck('invoice_number');
            foreach ($proformas as $number) {
                $result['proformas'][] = $number;
            }
        }

        return $result;
    }

    /** True when the lease's rent is the unit rent divided by a whole number of tenants (not hand-negotiated). */
    private function isEvenShare(Lease $lease, Unit $unit, int $n): bool
    {
        for ($k = 1; $k <= $n + 2; $k++) {
            if (abs((float) $lease->monthly_rent - round((float) $unit->rent / $k, 2)) < 0.01) {
                return true;
            }
        }

        return false;
    }

    private function repriceFutureInstallments(Lease $lease, float $monthlyTotal): void
    {
        LeaseInstallment::where('lease_id', $lease->id)
            ->whereNull('invoice_id')
            ->where('paid_amount', '<=', 0)
            ->whereDate('period_end', '>=', Carbon::today())
            ->get()
            ->each(function (LeaseInstallment $installment) use ($monthlyTotal) {
                $start  = Carbon::parse($installment->period_start);
                $close  = Carbon::parse($installment->period_end)->addDay();
                $months = max(1, (int) floor($start->diffInMonths($close)));
                $installment->update(['amount' => round($monthlyTotal * $months, 2)]);
            });
    }
}
