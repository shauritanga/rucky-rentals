<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add currency fields to invoices and payments
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('currency', ['USD', 'TZS'])->default('USD')->after('total');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency'); // Rate used for conversion
            $table->decimal('total_in_base', 10, 2)->nullable()->after('exchange_rate'); // TZS converted amount
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('currency', ['USD', 'TZS'])->default('USD')->after('amount');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency');
            $table->decimal('amount_in_base', 10, 2)->nullable()->after('exchange_rate'); // TZS converted amount
        });

        // Store exchange rates per property per date
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3); // USD
            $table->string('to_currency', 3);   // TZS
            $table->decimal('rate', 10, 4);     // 1 USD = X TZS
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['property_id', 'from_currency', 'to_currency', 'effective_date']);
            $table->index(['property_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'amount_in_base']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'total_in_base']);
        });

        Schema::dropIfExists('exchange_rates');
    }
};
