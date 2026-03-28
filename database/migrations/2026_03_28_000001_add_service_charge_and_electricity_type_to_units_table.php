<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('service_charge_per_sqm', 12, 2)->default(0)->after('rate_per_sqm');
            $table->decimal('service_charge', 12, 2)->default(0)->after('deposit');
            $table->enum('electricity_type', ['direct', 'submeter'])->default('direct')->after('service_charge');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['service_charge_per_sqm', 'service_charge', 'electricity_type']);
        });
    }
};
