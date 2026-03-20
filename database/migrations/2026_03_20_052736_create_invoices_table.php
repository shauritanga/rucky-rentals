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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->enum('type', ['invoice', 'proforma'])->default('invoice');
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_name');
            $table->string('tenant_email')->nullable();
            $table->string('unit_ref');
            $table->date('issued_date');
            $table->date('due_date')->nullable();
            $table->string('period')->nullable();
            $table->enum('status', ['draft', 'proforma', 'unpaid', 'paid', 'overdue'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
