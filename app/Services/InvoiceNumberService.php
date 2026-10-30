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
     * Per-tenant, per-year rent number: <CODE>/NN/YYYY (e.g. TIA/01/2026).
     * Must be called inside a DB::transaction (advisory lock).
     */
    public function generateForTenant(?Tenant $tenant, ?string $fallbackName = null, $date = null): string
    {
        [$code, $year] = $this->codeAndYear($tenant, $fallbackName, $date);

        return $code . '/' . str_pad((string) ($this->highestSequence("{$code}/", "/{$year}") + 1), 2, '0', STR_PAD_LEFT) . '/' . $year;
    }

    /**
     * Security deposit proforma: <CODE>/DEPOSIT/YYYY (e.g. TIA/DEPOSIT/2026). A tenant's second
     * deposit in the same year becomes <CODE>/DEPOSIT/02/YYYY, then /03/ and so on.
     */
    public function generateForDeposit(?Tenant $tenant, ?string $fallbackName = null, $date = null): string
    {
        [$code, $year] = $this->codeAndYear($tenant, $fallbackName, $date);
        $base = "{$code}/DEPOSIT/{$year}";

        if (!Invoice::where('invoice_number', $base)->lockForUpdate()->exists()) {
            return $base;
        }

        $next = max(1, $this->highestSequence("{$code}/DEPOSIT/", "/{$year}")) + 1;

        return $code . '/DEPOSIT/' . str_pad((string) $next, 2, '0', STR_PAD_LEFT) . '/' . $year;
    }

    /**
     * Electricity invoice: <CODE>/ELC/NN/YYYY (e.g. TIA/ELC/01/2026), counted per tenant per year
     * separately from rent numbers.
     */
    public function generateForElectricity(?Tenant $tenant, ?string $fallbackName = null, $date = null): string
    {
        [$code, $year] = $this->codeAndYear($tenant, $fallbackName, $date);

        return $code . '/ELC/' . str_pad((string) ($this->highestSequence("{$code}/ELC/", "/{$year}") + 1), 2, '0', STR_PAD_LEFT) . '/' . $year;
    }

    /** Resolve the tenant's invoice code and the year, taking the advisory lock first. */
    private function codeAndYear(?Tenant $tenant, ?string $fallbackName, $date): array
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

        return [$code, $year];
    }

    /** Highest NN in numbers shaped <prefix>NN<suffix>; 0 when there are none. */
    private function highestSequence(string $prefix, string $suffix): int
    {
        return Invoice::query()
            ->where('invoice_number', 'like', $prefix . '%' . $suffix)
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(fn ($n) => preg_match('/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/', (string) $n, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;
    }
}
