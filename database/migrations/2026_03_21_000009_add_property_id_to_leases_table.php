<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
        });

        // Backfill existing leases from their unit's property assignment.
        DB::statement('UPDATE leases SET property_id = units.property_id FROM units WHERE units.id = leases.unit_id AND leases.property_id IS NULL');

        // Legacy fallback: if only one property exists, assign remaining null rows.
        $propertyCount = DB::table('properties')->count();
        if ($propertyCount === 1) {
            $propertyId = DB::table('properties')->value('id');
            DB::table('leases')->whereNull('property_id')->update(['property_id' => $propertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
