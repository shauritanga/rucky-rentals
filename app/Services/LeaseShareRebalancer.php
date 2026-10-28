<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaseShareRebalancer
{
    private const RUNNING = ['active', 'expiring', 'overdue'];

    /**
     * Price every unbilled installment of a unit's running leases by how many leases share
     * the unit in each month, so a tenant's share returns to 100% once a co-tenant's lease
     * ends. Installments already invoiced, paid or past are never touched.
     *
     * @return array{updated: string[], skipped: string[], proformas: string[]}
     */
    public function rebalance(Unit $unit, ?Lease $activating = null): array
    {
        $result = ['updated' => [], 'skipped' => [], 'proformas' => []];

        $leases = $this->runningLeases($unit);
        if ($leases->isEmpty()) {
            return $result;
        }

        $today = Carbon::today();

        foreach ($leases as $lease) {
            if (!$this->isEvenShare($lease, $unit, $leases->count())) {
                $result['skipped'][] = $lease->lease_number;
                continue;
            }

            $changed = false;

            LeaseInstallment::where('lease_id', $lease->id)
                ->whereNull('invoice_id')
                ->where('paid_amount', '<=', 0)
                ->whereDate('period_end', '>=', $today)
                ->orderBy('sequence')
                ->get()
                ->each(function (LeaseInstallment $installment) use ($unit, $leases, &$changed) {
                    $amount = $this->periodAmount(
                        $unit,
                        $leases,
                        Carbon::parse($installment->period_start),
                        Carbon::parse($installment->period_end)->addDay()
                    );
                    if (abs($amount - (float) $installment->amount) >= 0.01) {
                        $installment->update(['amount' => $amount]);
                        $changed = true;
                    }
                });

            // Keep the lease's own headline figures at today's share.
            $n = $this->shareCount($leases, max($today, Carbon::parse($lease->rent_start_date ?: $lease->start_date)));
            $rent = round((float) $unit->rent / $n, 2);
            $sc   = round((float) $unit->service_charge / $n, 2);
            if (abs((float) $lease->monthly_rent - $rent) >= 0.01 || abs($lease->monthly_service_charge - $sc) >= 0.01) {
                $lease->update(['monthly_rent' => $rent, 'service_charge' => $sc]);
                $changed = true;
            }

            if ($changed) {
                $result['updated'][] = $lease->lease_number;
                Invoice::where('lease_id', $lease->id)
                    ->where('type', 'proforma')
                    ->whereDate('due_date', '>=', $today)
                    ->whereDoesntHave('payments')
                    ->pluck('invoice_number')
                    ->each(function ($number) use (&$result) {
                        $result['proformas'][] = $number;
                    });
            }
        }

        return $result;
    }

    /** Per-month rent and service charge for a lease over a period, or null for a custom-rent lease. */
    public function monthlyShareFor(Lease $lease, Carbon $start, Carbon $close): ?array
    {
        $unit = $lease->unit;
        if (!$unit) {
            return null;
        }

        $leases = $this->runningLeases($unit);
        if (!$leases->contains('id', $lease->id)) {
            $leases->push($lease);
        }
        if (!$this->isEvenShare($lease, $unit, $leases->count())) {
            return null;
        }

        $months = max(1, (int) floor($start->diffInMonths($close)));
        $rent = $sc = 0.0;
        for ($i = 0; $i < $months; $i++) {
            $n = $this->shareCount($leases, $start->copy()->addMonths($i));
            $rent += (float) $unit->rent / $n;
            $sc   += (float) $unit->service_charge / $n;
        }

        return ['rent' => round($rent / $months, 2), 'service_charge' => round($sc / $months, 2)];
    }

    private function runningLeases(Unit $unit): Collection
    {
        return $unit->leases()->whereIn('status', self::RUNNING)->get();
    }

    /** Leases covering the unit on a date (at least 1, so a lone tenant pays in full). */
    private function shareCount(Collection $leases, Carbon $date): int
    {
        $n = $leases->filter(fn (Lease $l) =>
            Carbon::parse($l->rent_start_date ?: $l->start_date)->lte($date)
            && Carbon::parse($l->end_date)->gt($date)
        )->count();

        return max(1, $n);
    }

    /** Sum of each month's share of (rent + service charge) over [start, close). */
    private function periodAmount(Unit $unit, Collection $leases, Carbon $start, Carbon $close): float
    {
        $monthly = (float) $unit->rent + (float) $unit->service_charge;
        $months  = max(1, (int) floor($start->diffInMonths($close)));
        $total   = 0.0;
        for ($i = 0; $i < $months; $i++) {
            $total += $monthly / $this->shareCount($leases, $start->copy()->addMonths($i));
        }

        return round($total, 2);
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
}
