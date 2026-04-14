<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add property_id to maintenance_tickets
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Populate property_id from related units
        DB::statement('
            UPDATE maintenance_tickets
            SET property_id = (
                SELECT property_id FROM units WHERE units.id = maintenance_tickets.unit_id
            )
            WHERE property_id IS NULL
        ');

        // For maintenance_tickets without unit_id, assign to default property
        $defaultPropertyId = DB::table('properties')->orderBy('id')->value('id');
        if (!empty($defaultPropertyId)) {
            DB::table('maintenance_tickets')
                ->whereNull('property_id')
                ->update(['property_id' => $defaultPropertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
