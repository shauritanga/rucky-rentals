<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A manager's new payment, edit or deletion waits here until a superuser decides; the payment itself is untouched until then. */
    public function up(): void
    {
        Schema::create('payment_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete(); // null = a payment not recorded yet (or one since deleted)
            $table->foreignId('property_id')->nullable();
            $table->string('type', 10);                       // create | edit | delete
            $table->json('payload')->nullable();              // create: the payment to record; edit: proposed fields; always carries a 'snapshot' of who/what it is about
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_change_requests');
    }
};
