<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->date('possession_date')->nullable()->after('currency');
            $table->date('rent_start_date')->nullable()->after('possession_date');
            $table->boolean('fitout_enabled')->default(false)->after('rent_start_date');
            $table->date('fitout_to_date')->nullable()->after('fitout_enabled');
            $table->unsignedInteger('fitout_days')->default(0)->after('fitout_to_date');
            $table->decimal('wht_rate', 5, 2)->default(10)->after('fitout_days');
            $table->decimal('service_charge_rate', 5, 2)->default(5)->after('wht_rate');
            $table->decimal('vat_rate', 5, 2)->default(18)->after('service_charge_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn([
                'possession_date',
                'rent_start_date',
                'fitout_enabled',
                'fitout_to_date',
                'fitout_days',
                'wht_rate',
                'service_charge_rate',
                'vat_rate',
            ]);
        });
    }
};
