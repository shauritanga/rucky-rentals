<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')->update(['total_floors' => 7]);
        DB::statement('ALTER TABLE properties ALTER COLUMN total_floors SET DEFAULT 7');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE properties ALTER COLUMN total_floors SET DEFAULT 1');
    }
};
