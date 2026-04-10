<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill item_type on existing electricity invoice items created before
        // the electricity_charge / electricity_vat types were introduced.

        DB::table('invoice_items')
            ->whereIn('description', [
                'Electricity — Generator Charge',
                'Electricity — Submeter Sale',   // old single-item submeter (gross)
                'Electricity — Submeter Charge', // new two-item submeter (net)
            ])
            ->whereNull('item_type')
            ->update(['item_type' => 'electricity_charge']);

        DB::table('invoice_items')
            ->whereIn('description', [
                'Electricity — Generator VAT',
                'Electricity — Submeter VAT',
            ])
            ->whereNull('item_type')
            ->update(['item_type' => 'electricity_vat']);
    }

    public function down(): void
    {
        DB::table('invoice_items')
            ->whereIn('item_type', ['electricity_charge', 'electricity_vat'])
            ->update(['item_type' => null]);
    }
};
