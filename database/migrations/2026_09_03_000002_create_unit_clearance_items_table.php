<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_clearance_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('room')->nullable();
            $table->string('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('responsible_party')->default('tenant'); // tenant|landlord
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_clearance_items');
    }
};
