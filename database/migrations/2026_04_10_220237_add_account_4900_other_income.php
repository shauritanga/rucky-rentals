<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add account 4900 (Other Income) to every property that already has
 * a chart of accounts, so it appears in the accounts list immediately
 * rather than waiting for a transaction to auto-create it.
 *
 * Also corrects any old 4200 rows still named "Other Income" that the
 * previous migration may have missed (e.g. on the live server).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure any remaining stale 4200 "Other Income" rows are corrected
        DB::table('accounts')
            ->where('code', '4200')
            ->where('name', 'Other Income')
            ->update([
                'name'     => 'Electricity Income',
                'type'     => 'revenue',
                'category' => 'Operating Revenue',
            ]);

        // Seed 4900 for every property that already has at least one account
        $propertyIds = DB::table('accounts')
            ->distinct()
            ->pluck('property_id');

        foreach ($propertyIds as $propertyId) {
            $exists = DB::table('accounts')
                ->where('property_id', $propertyId)
                ->where('code', '4900')
                ->exists();

            if (!$exists) {
                DB::table('accounts')->insert([
                    'property_id'  => $propertyId,
                    'code'         => '4900',
                    'name'         => 'Other Income',
                    'type'         => 'revenue',
                    'category'     => 'Other Revenue',
                    'balance'      => 0,
                    'ytd_activity' => 0,
                    'description'  => 'Auto-created by accounting automation.',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('accounts')
            ->where('code', '4900')
            ->where('name', 'Other Income')
            ->delete();
    }
};
