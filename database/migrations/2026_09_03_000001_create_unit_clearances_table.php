<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('clearance_number')->unique();
            $table->string('status')->default('scheduled'); // scheduled|in_progress|completed|cancelled
            $table->date('scheduled_date')->nullable();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->json('inspection_checklist')->nullable();
            $table->string('currency')->default('TZS');
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->decimal('shortfall_amount', 15, 2)->default(0);
            $table->text('manager_notes')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->string('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_clearances');
    }
};
