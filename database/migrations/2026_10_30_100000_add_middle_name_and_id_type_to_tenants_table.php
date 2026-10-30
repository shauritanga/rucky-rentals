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
            $table->string('middle_name')->nullable();
            // national | voter | passport | driving_license | other (legacy free-text IDs)
            $table->string('id_type', 20)->nullable();
        });

        // Existing tenants only had one free-text ID field, so keep it as a legacy "other" ID.
        DB::table('tenants')->whereNotNull('national_id')->where('national_id', '!=', '')->update(['id_type' => 'other']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'id_type']);
        });
    }
};
