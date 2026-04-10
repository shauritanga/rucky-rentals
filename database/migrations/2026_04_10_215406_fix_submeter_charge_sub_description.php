<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrite sub_description on electricity_charge invoice items.
 *
 * Old: "400.00 units × TZS 500.00 (net of 18.00% VAT)"
 * New: "400.00 units × TZS 423.73/unit"
 *
 * We join electricity_sales → invoice_items via invoice_id and
 * recompute net_unit_price = (amount / 1.18) / units_sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('invoice_items as ii')
            ->join('electricity_sales as es', 'es.invoice_id', '=', 'ii.invoice_id')
            ->where('ii.item_type', 'electricity_charge')
            ->where('ii.description', 'Electricity — Submeter Charge')
            ->select('ii.id', 'es.units_sold', 'es.amount')
            ->get();

        foreach ($rows as $row) {
            $unitsSold    = (float) $row->units_sold;
            $gross        = (float) $row->amount;
            $netTotal     = $gross / 1.18;
            $netUnitPrice = $unitsSold > 0 ? $netTotal / $unitsSold : 0;

            DB::table('invoice_items')->where('id', $row->id)->update([
                'sub_description' => number_format($unitsSold, 2, '.', '')
                    . ' units × TZS '
                    . number_format($netUnitPrice, 2, '.', '')
                    . '/unit',
            ]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('invoice_items as ii')
            ->join('electricity_sales as es', 'es.invoice_id', '=', 'ii.invoice_id')
            ->where('ii.item_type', 'electricity_charge')
            ->where('ii.description', 'Electricity — Submeter Charge')
            ->select('ii.id', 'es.units_sold', 'es.unit_price')
            ->get();

        foreach ($rows as $row) {
            DB::table('invoice_items')->where('id', $row->id)->update([
                'sub_description' => number_format((float) $row->units_sold, 2, '.', '')
                    . ' units × TZS '
                    . number_format((float) $row->unit_price, 2, '.', '')
                    . ' (net of 18.00% VAT)',
            ]);
        }
    }
};
