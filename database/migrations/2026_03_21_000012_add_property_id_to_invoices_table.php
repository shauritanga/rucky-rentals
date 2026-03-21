<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('type')->constrained('properties')->nullOnDelete();
        });

        // Backfill from linked lease when available.
        DB::statement('UPDATE invoices SET property_id = leases.property_id FROM leases WHERE leases.id = invoices.lease_id AND invoices.property_id IS NULL');

        // Legacy fallback: if only one property exists, map remaining null records to it.
        $propertyCount = DB::table('properties')->count();
        if ($propertyCount === 1) {
            $propertyId = DB::table('properties')->value('id');
            DB::table('invoices')->whereNull('property_id')->update(['property_id' => $propertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
