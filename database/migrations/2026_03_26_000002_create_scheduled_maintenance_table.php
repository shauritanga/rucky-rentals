<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('unit_ref')->nullable();
            $table->string('category');
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'biannual', 'annual']);
            $table->date('next_due');
            $table->string('assignee')->nullable();
            $table->enum('status', ['upcoming', 'overdue', 'completed'])->default('upcoming');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_maintenance');
    }
};
