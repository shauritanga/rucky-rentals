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
        // Use the first available property — skip silently if none exist yet
        $propertyId = DB::table('properties')->value('id');
        if (! $propertyId) return;

        DB::table('exchange_rates')->insertOrIgnore([
            [
                'property_id'    => $propertyId,
                'from_currency'  => 'USD',
                'to_currency'    => 'TZS',
                'rate'           => 2500.00,
                'effective_date' => now()->toDateString(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $propertyId = DB::table('properties')->value('id');
        if (! $propertyId) return;

        DB::table('exchange_rates')->where([
            ['property_id',   '=', $propertyId],
            ['from_currency', '=', 'USD'],
            ['to_currency',   '=', 'TZS'],
        ])->delete();
    }
};
