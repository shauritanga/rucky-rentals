<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Advisory lock key shared by every invoice number generator.
     * Matches the lock used in the old InvoiceController private methods.
     */
    private const LOCK_KEY = 856331;

    /**
     * Generate the next available invoice number with the given prefix.
     * Must be called inside a DB::transaction to ensure the advisory lock works.
     *
     * Examples:
     *   generateNumber('INV') => 'INV-0001'
     *   generateNumber('PF')  => 'PF-0002'
     *   generateNumber('ELEC')=> 'ELEC-0003'
     */
    public function generateNumber(string $prefix): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
        }

        $max = Invoice::query()
            ->select('invoice_number')
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(fn ($n) => preg_match('/(\d+)$/', (string) $n, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return $prefix . '-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Per-tenant, per-year number: <CODE>/NN/YYYY (e.g. TIA/01/2026).
     * Must be called inside a DB::transaction (advisory lock).
     */
    public function generateForTenant(?Tenant $tenant, ?string $fallbackName = null, $date = null): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
        }

        $tenant ??= $fallbackName
            ? Tenant::where('name', $fallbackName)->orWhere('company_name', $fallbackName)->first()
            : null;

        $code = $tenant?->invoice_code
            ?: Tenant::generateInvoiceCode((string) ($fallbackName ?: 'Tenant'), $tenant?->id);
        $year = $date ? Carbon::parse($date)->year : now()->year;
        $suffix = '/' . $year;
        $prefix = $code . '/';

        $max = Invoice::query()
            ->where('invoice_number', 'like', $prefix . '%' . $suffix)
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(fn ($n) => preg_match('/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/', (string) $n, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return $prefix . str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT) . $suffix;
    }
}
