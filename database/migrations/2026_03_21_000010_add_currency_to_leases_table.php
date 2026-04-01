<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('payment_cycle');
        });

        // Backfill existing leases from their linked unit currency.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE leases
                SET currency = (
                    SELECT currency FROM units WHERE units.id = leases.unit_id
                )
                WHERE currency IS NULL OR currency = "USD"
            ');
        } else {
            DB::statement('UPDATE leases SET currency = units.currency FROM units WHERE units.id = leases.unit_id');
        }
        DB::table('leases')->whereNull('currency')->update(['currency' => 'USD']);
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
