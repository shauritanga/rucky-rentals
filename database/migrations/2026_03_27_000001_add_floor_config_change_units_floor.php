<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Properties: add floor_config JSON ────────────────────────────────
        Schema::table('properties', function (Blueprint $table) {
            $table->json('floor_config')->nullable()->after('total_floors');
        });

        // Backfill: convert existing total_floors into a floor_config JSON
        DB::table('properties')->orderBy('id')->each(function ($property) {
            $upperFloors = max(1, (int) ($property->total_floors ?? 7));
            DB::table('properties')->where('id', $property->id)->update([
                'floor_config' => json_encode([
                    'basements'       => 0,
                    'has_ground_floor'=> false,
                    'has_mezzanine'   => false,
                    'upper_floors'    => $upperFloors,
                ]),
            ]);
        });

        // ── Units: change `floor` from integer to varchar(20) ────────────────
        // Step 1: add a temporary string column
        Schema::table('units', function (Blueprint $table) {
            $table->string('floor_code', 20)->nullable()->after('floor');
        });

        // Step 2: backfill — cast existing integer floor values to string
        DB::statement('UPDATE units SET floor_code = CAST(floor AS VARCHAR)');

        // Step 3: drop the old integer column
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('floor');
        });

        // Step 4: rename floor_code → floor
        Schema::table('units', function (Blueprint $table) {
            $table->renameColumn('floor_code', 'floor');
        });

        // Step 5: make non-nullable now that all rows are backfilled
        Schema::table('units', function (Blueprint $table) {
            $table->string('floor', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // ── Units: revert varchar floor → integer ────────────────────────────
        Schema::table('units', function (Blueprint $table) {
            $table->integer('floor_int')->nullable()->after('floor');
        });

        // Cast string codes back to integers (only works for numeric codes; others default to 1)
        DB::statement("UPDATE units SET floor_int = CASE WHEN floor ~ '^[0-9]+$' THEN CAST(floor AS INTEGER) ELSE 1 END");

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('floor');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->renameColumn('floor_int', 'floor');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->integer('floor')->nullable(false)->change();
        });

        // ── Properties: remove floor_config ──────────────────────────────────
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('floor_config');
        });
    }
};
