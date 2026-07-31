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
            DB::statement('ALTER TABLE units ALTER COLUMN size_sqm TYPE numeric(12,4)');
        } else {
            DB::statement('ALTER TABLE units MODIFY size_sqm DECIMAL(12,4) NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE units ALTER COLUMN size_sqm TYPE numeric(10,2)');
        } else {
            DB::statement('ALTER TABLE units MODIFY size_sqm DECIMAL(10,2) NULL');
        }
    }
};
