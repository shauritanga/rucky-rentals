<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lease_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->enum('currency', ['USD', 'TZS'])->default('USD');
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['lease_id', 'sequence']);
            $table->index(['lease_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_installments');
    }
};
