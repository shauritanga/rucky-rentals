<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Deposit = 1 month rent + 1 month service charge (correct business rule).
        // Remove the orphaned deposit_multiplier key (superseded by the two separate settings).
        DB::table('system_settings')
            ->where('key', 'deposit_service_charge_months')
            ->update(['value' => '1']);

        DB::table('system_settings')
            ->where('key', 'deposit_multiplier')
            ->delete();
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'deposit_service_charge_months')
            ->update(['value' => '1']);

        DB::table('system_settings')->insert([
            'key'        => 'deposit_multiplier',
            'value'      => '2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
