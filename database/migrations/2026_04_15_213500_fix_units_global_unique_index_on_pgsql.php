<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

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
