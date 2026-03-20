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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_months');
            $table->integer('payment_cycle'); // 3, 4, 6, 12
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('deposit', 10, 2);
            $table->enum('status', ['pending_accountant', 'pending_pm', 'active', 'expiring', 'overdue', 'rejected', 'terminated'])->default('pending_accountant');
            $table->text('terms')->nullable();
            $table->json('approval_log')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
