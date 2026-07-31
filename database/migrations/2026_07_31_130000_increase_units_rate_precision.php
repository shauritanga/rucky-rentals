<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite stores NUMERIC as dynamic type; no column alteration needed.
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE units ALTER COLUMN rate_per_sqm TYPE numeric(12,4)');
            DB::statement('ALTER TABLE units ALTER COLUMN service_charge_per_sqm TYPE numeric(12,4)');
        } else {
            DB::statement('ALTER TABLE units MODIFY rate_per_sqm DECIMAL(12,4) NULL');
            DB::statement('ALTER TABLE units MODIFY service_charge_per_sqm DECIMAL(12,4) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE units ALTER COLUMN rate_per_sqm TYPE numeric(12,2)');
            DB::statement('ALTER TABLE units ALTER COLUMN service_charge_per_sqm TYPE numeric(12,2)');
        } else {
            DB::statement('ALTER TABLE units MODIFY rate_per_sqm DECIMAL(12,2) NULL');
            DB::statement('ALTER TABLE units MODIFY service_charge_per_sqm DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
    }
};
