<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->upsert(
            [
                ['key' => 'deposit_rent_months',           'value' => '1'],
                ['key' => 'deposit_service_charge_months', 'value' => '1'],
            ],
            ['key'],
            ['value']
        );
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', ['deposit_rent_months', 'deposit_service_charge_months'])
            ->delete();
    }
};
