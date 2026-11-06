<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant's credit ledger. An overpayment adds to it (+), applying credit to a later invoice or refunding the
     * tenant takes from it (-). The balance is the sum of a tenant's rows.
     */
    public function up(): void
    {
        Schema::create('tenant_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable();
            $table->decimal('amount', 14, 2);                     // + overpayment, - applied / refunded
            $table->string('type', 14);                           // overpayment | applied | refund
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // the overpaid invoice
            $table->foreignId('target_invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // where it was applied
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();         // the credit payment (applied)
            $table->string('method', 50)->nullable();             // refund
            $table->string('reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index('source_invoice_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            // cash = money received; credit = an overpayment applied to this invoice (no cash moves).
            $table->string('source', 10)->default('cash')->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::dropIfExists('tenant_credits');
    }
};
