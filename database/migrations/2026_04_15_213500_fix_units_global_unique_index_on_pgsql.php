<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasIndex('units', 'units_unit_number_unique')) {
                Schema::table('units', fn ($table) => $table->dropUnique('units_unit_number_unique'));
            }
            if (!Schema::hasIndex('units', 'units_property_id_unit_number_unique')) {
                Schema::table('units', fn ($table) => $table->unique(['property_id', 'unit_number'], 'units_property_id_unit_number_unique'));
            }
            return;
        }

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS units_unit_number_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS units_property_id_unit_number_unique ON units (property_id, unit_number)');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS units_property_id_unit_number_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS units_unit_number_unique ON units (unit_number)');
    }
};
