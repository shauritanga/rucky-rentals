<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A manager's edit or deletion of a live lease waits here until a superuser decides. */
    public function up(): void
    {
        Schema::create('lease_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable();
            $table->string('type', 10);                       // edit | delete
            $table->json('payload')->nullable();              // the proposed lease fields (edit)
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_change_requests');
    }
};
