<?php

namespace App\Observers;

use App\Models\Lease;
use App\Models\Unit;

/**
 * Keeps units.status in step with its leases. PostgreSQL does this with database triggers;
 * hosts without them (MySQL on shared hosting) rely on this observer. Running it on every
 * driver is harmless because both compute the same result.
 */
class LeaseObserver
{
    public function created(Lease $lease): void
    {
        Unit::where('id', $lease->unit_id)->update(['status' => 'occupied']);
    }

    public function updated(Lease $lease): void
    {
        $this->sync($lease->unit_id);
        if ($lease->wasChanged('unit_id') && $lease->getOriginal('unit_id')) {
            $this->sync((int) $lease->getOriginal('unit_id'));
        }
    }

    public function deleted(Lease $lease): void
    {
        $this->sync($lease->unit_id);
    }

    public function restored(Lease $lease): void
    {
        $this->sync($lease->unit_id);
    }

    private function sync($unitId): void
    {
        if (!$unitId) {
            return;
        }

        $running = Lease::where('unit_id', $unitId)->whereIn('status', ['active', 'expiring', 'overdue'])->exists();
        Unit::where('id', $unitId)->update(['status' => $running ? 'occupied' : 'vacant']);
    }
}
