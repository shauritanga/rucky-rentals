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
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('unit_ref'); // unit number or "Common"
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category'); // Plumbing, Electrical, HVAC, General, Security
            $table->enum('priority', ['high', 'med', 'low'])->default('med');
            $table->enum('status', ['open', 'in-progress', 'resolved'])->default('open');
            $table->string('assignee')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('reported_date');
            $table->json('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
