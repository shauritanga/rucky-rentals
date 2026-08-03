<?php

namespace App\Services;

use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class LeaseNumberService
{
    /**
     * Advisory lock key shared by every lease number generator.
     */
    private const LOCK_KEY = 856332;

    /**
     * Generate the next available lease number.
     * Must be called inside a DB::transaction to ensure the advisory lock works.
     *
     * Example: generateNumber() => 'LEASE-0001'
     */
    public function generateNumber(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
        }

        $max = Lease::withTrashed()
            ->select('lease_number')
            ->lockForUpdate()
            ->pluck('lease_number')
            ->map(fn ($n) => preg_match('/(\d+)$/', (string) $n, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'LEASE-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
