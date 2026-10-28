<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * USD now lives only on units. Invoices and payments are TZS; the rate typed on a
 * proforma is kept in invoices.usd_rate for audit. Existing USD records are
 * converted using the rate each one already stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('usd_rate', 12, 4)->nullable()->after('exchange_rate');
        });

        DB::transaction(function () {
            $invoices = DB::table('invoices')->where('currency', '!=', 'TZS')->get();
            foreach ($invoices as $invoice) {
                $rate = (float) ($invoice->exchange_rate ?: 1);

                DB::table('invoice_items')->where('invoice_id', $invoice->id)->get()->each(function ($item) use ($rate) {
                    DB::table('invoice_items')->where('id', $item->id)->update([
                        'unit_price' => round((float) $item->unit_price * $rate, 2),
                        'total'      => round((float) $item->total * $rate, 2),
                    ]);
                });

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'usd_rate'      => $rate,
                    'currency'      => 'TZS',
                    'exchange_rate' => 1,
                ]);
            }

            DB::table('payments')->where('currency', '!=', 'TZS')->get()->each(function ($payment) {
                $base = $payment->amount_in_base ?? round((float) $payment->amount * (float) ($payment->exchange_rate ?: 1), 2);
                DB::table('payments')->where('id', $payment->id)->update([
                    'amount'         => $base,
                    'currency'       => 'TZS',
                    'exchange_rate'  => 1,
                    'amount_in_base' => $base,
                ]);
            });

            DB::table('receipts')->where('currency', '!=', 'TZS')->get()->each(function ($receipt) {
                $payment = DB::table('payments')->where('id', $receipt->payment_id)->first();
                DB::table('receipts')->where('id', $receipt->id)->update([
                    'amount'   => $payment?->amount ?? $receipt->amount,
                    'currency' => 'TZS',
                ]);
            });

            // total_in_base = items total (+ lease VAT is already inside item math elsewhere; keep stored value
            // for invoices that already had one, derived from the rate it was posted with).
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('usd_rate');
        });
    }
};
