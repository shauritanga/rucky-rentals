<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outages', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Backfill: assign existing outages to the first property (if only one exists)
        $propertyId = DB::table('properties')->value('id');
        if ($propertyId) {
            DB::table('outages')->whereNull('property_id')->update(['property_id' => $propertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('outages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
