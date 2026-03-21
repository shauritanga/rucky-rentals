<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
        });

        // Legacy backfill: if only one property exists, map existing tenants to it.
        $propertyCount = DB::table('properties')->count();
        if ($propertyCount === 1) {
            $propertyId = DB::table('properties')->value('id');
            DB::table('tenants')->whereNull('property_id')->update(['property_id' => $propertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
