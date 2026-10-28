<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 'rent' = normal billing, 'deposit' = refundable security deposit (non-tax proforma + receipt).
            $table->string('kind', 20)->default('rent')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
