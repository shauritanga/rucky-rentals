<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $today = Carbon::today();

        $leases = DB::table('leases')
            ->whereIn('status', ['active', 'expiring', 'overdue'])
            ->orderBy('id')
            ->get();

        foreach ($leases as $lease) {
            $exists = DB::table('lease_installments')
                ->where('lease_id', $lease->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $cycleMonths = max(1, (int) ($lease->payment_cycle ?: 1));
            $monthlyRent = (float) ($lease->monthly_rent ?: 0);
            $currency = strtoupper((string) ($lease->currency ?: 'USD'));
            if (!in_array($currency, ['USD', 'TZS'], true)) {
                $currency = 'USD';
            }

            $start = Carbon::parse($lease->rent_start_date ?: $lease->start_date)->startOfDay();
            $end = Carbon::parse($lease->end_date)->startOfDay();

            if ($end->lte($start)) {
                continue;
            }

            $invoices = DB::table('invoices')
                ->where('lease_id', $lease->id)
                ->where('type', '!=', 'proforma')
                ->orderByRaw('COALESCE(due_date, issued_date) asc')
                ->orderBy('id')
                ->get()
                ->all();

            $usedInvoiceIds = [];
            $cursor = $start->copy();
            $sequence = 1;

            while ($cursor->lt($end) && $sequence <= 120) {
                $periodStart = $cursor->copy();
                $nextCursor = $cursor->copy()->addMonths($cycleMonths);
                $periodClose = $nextCursor->lte($end) ? $nextCursor : $end->copy();
                $periodEnd = $periodClose->copy()->subDay();

                $monthsInPeriod = max(1, $periodStart->diffInMonths($periodClose));
                $amount = round($monthlyRent * $monthsInPeriod, 2);

                $matchedInvoice = null;

                foreach ($invoices as $invoice) {
                    if (in_array((int) $invoice->id, $usedInvoiceIds, true)) {
                        continue;
                    }

                    if (!empty($invoice->due_date) && Carbon::parse($invoice->due_date)->toDateString() === $periodStart->toDateString()) {
                        $matchedInvoice = $invoice;
                        break;
                    }
                }

                if (!$matchedInvoice) {
                    foreach ($invoices as $invoice) {
                        if (!in_array((int) $invoice->id, $usedInvoiceIds, true)) {
                            $matchedInvoice = $invoice;
                            break;
                        }
                    }
                }

                $paidAmount = 0.0;
                $status = $periodStart->lt($today) ? 'overdue' : 'unpaid';
                $invoiceId = null;

                if ($matchedInvoice) {
                    $invoiceId = (int) $matchedInvoice->id;
                    $usedInvoiceIds[] = $invoiceId;

                    $invoiceTotal = (float) DB::table('invoice_items')
                        ->where('invoice_id', $invoiceId)
                        ->sum('total');

                    $paidAmount = (float) DB::table('payments')
                        ->where('invoice_id', $invoiceId)
                        ->where('status', 'paid')
                        ->sum('amount');

                    if ($invoiceTotal > 0 && $paidAmount + 0.00001 >= $invoiceTotal) {
                        $status = 'paid';
                    } elseif ($paidAmount > 0) {
                        $status = 'partially_paid';
                    } else {
                        $isOverdue = !empty($matchedInvoice->due_date)
                            ? Carbon::today()->gt(Carbon::parse($matchedInvoice->due_date)->startOfDay())
                            : $periodStart->lt($today);
                        $status = $isOverdue ? 'overdue' : 'unpaid';
                    }
                }

                DB::table('lease_installments')->insert([
                    'property_id' => $lease->property_id,
                    'lease_id' => $lease->id,
                    'invoice_id' => $invoiceId,
                    'sequence' => $sequence,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'due_date' => $periodStart->toDateString(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'paid_amount' => round(max(0, $paidAmount), 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $cursor = $nextCursor;
                $sequence += 1;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid removing legitimate installment records.
    }
};
