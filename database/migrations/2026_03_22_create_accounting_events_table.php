<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // 'invoice_issued', 'invoice_voided', 'payment_posted', 'payment_voided', 'entry_created'
            $table->string('entity_type'); // 'Invoice', 'Payment', 'MaintenanceTicket', 'JournalEntry'
            $table->unsignedBigInteger('entity_id');
            $table->string('reference')->nullable(); // INV-123, PAY-456, MAINT-789
            $table->text('description')->nullable();
            $table->json('data'); // Full before/after state: {before: {...}, after: {...}, posted_lines: [...]}
            $table->json('posted_entries')->nullable(); // JournalEntry IDs created
            $table->string('status'); // 'success', 'failed', 'reversed'
            $table->text('error_message')->nullable(); // If failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->ipAddress()->nullable();
            $table->timestamps();

            $table->index(['property_id', 'event_type']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['reference']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_events');
    }
};
