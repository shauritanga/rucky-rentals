<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Backfill from the unit's property_id
        DB::statement('
            UPDATE meter_readings
            SET property_id = (
                SELECT property_id FROM units WHERE units.id = meter_readings.unit_id
            )
            WHERE property_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
