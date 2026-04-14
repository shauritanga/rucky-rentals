<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS units_unit_number_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS units_property_id_unit_number_unique ON units (property_id, unit_number)');

            return;
        }

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_unit_number_unique');
            $table->unique(['property_id', 'unit_number'], 'units_property_id_unit_number_unique');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS units_property_id_unit_number_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS units_unit_number_unique ON units (unit_number)');

            return;
        }

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_property_id_unit_number_unique');
            $table->unique('unit_number');
        });
    }
};
