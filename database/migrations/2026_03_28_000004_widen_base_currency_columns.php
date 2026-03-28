<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // decimal(10,2) max ~99M — too small for TZS amounts (1 USD ≈ 2500 TZS).
        // Widen to decimal(20,2) to safely hold hundreds of billions of TZS.
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_in_base', 20, 2)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_in_base', 20, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_in_base', 10, 2)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_in_base', 10, 2)->nullable()->change();
        });
    }
};
