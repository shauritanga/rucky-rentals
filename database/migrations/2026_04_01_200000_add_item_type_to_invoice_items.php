<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('item_type', 30)->default('other')->after('description');
        });

        // Back-fill existing rows based on description text matching
        DB::table('invoice_items')
            ->whereRaw("LOWER(description) LIKE '%service charge%'")
            ->update(['item_type' => 'service_charge']);

        DB::table('invoice_items')
            ->whereRaw("LOWER(description) NOT LIKE '%service charge%'")
            ->update(['item_type' => 'rent']);
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
