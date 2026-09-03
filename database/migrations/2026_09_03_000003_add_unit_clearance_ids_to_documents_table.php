<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('unit_clearance_id')
                ->nullable()
                ->after('maintenance_record_id')
                ->constrained('unit_clearances')
                ->nullOnDelete();
            $table->foreignId('unit_clearance_item_id')
                ->nullable()
                ->after('unit_clearance_id')
                ->constrained('unit_clearance_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_clearance_item_id');
            $table->dropConstrainedForeignId('unit_clearance_id');
        });
    }
};
