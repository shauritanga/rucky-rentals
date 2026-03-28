<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            // Drop the property-scoped foreign key and unique constraint
            $table->dropForeign(['property_id']);
            $table->dropUnique(['property_id', 'from_currency', 'to_currency', 'effective_date']);
            $table->dropIndex(['property_id', 'effective_date']);

            // Make property_id optional — rates are now global
            $table->unsignedBigInteger('property_id')->nullable()->change();

            // Global unique constraint: one rate per currency pair per date
            $table->unique(['from_currency', 'to_currency', 'effective_date'], 'exchange_rates_global_unique');
            $table->index(['from_currency', 'to_currency', 'effective_date'], 'exchange_rates_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropUnique('exchange_rates_global_unique');
            $table->dropIndex('exchange_rates_lookup_index');
            $table->unsignedBigInteger('property_id')->nullable(false)->change();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->unique(['property_id', 'from_currency', 'to_currency', 'effective_date']);
            $table->index(['property_id', 'effective_date']);
        });
    }
};
