<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_unit_number_unique');
            $table->unique(['property_id', 'unit_number'], 'units_property_id_unit_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_property_id_unit_number_unique');
            $table->unique('unit_number');
        });
    }
};
