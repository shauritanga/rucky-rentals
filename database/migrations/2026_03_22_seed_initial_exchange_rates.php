<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed default exchange rates for testing
        // Assumes property_id = 1 exists
        DB::table('exchange_rates')->insertOrIgnore([
            [
                'property_id' => 1,
                'from_currency' => 'USD',
                'to_currency' => 'TZS',
                'rate' => 2500.00, // 1 USD = 2,500 TZS (example rate)
                'effective_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('exchange_rates')->where([
            ['property_id', '=', 1],
            ['from_currency', '=', 'USD'],
            ['to_currency', '=', 'TZS'],
        ])->delete();
    }
};
