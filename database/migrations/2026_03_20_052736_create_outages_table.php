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
        Schema::create('outages', function (Blueprint $table) {
            $table->id();
            $table->date('outage_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['major', 'minor', 'planned'])->default('minor');
            $table->string('floors_affected')->default('All floors');
            $table->boolean('generator_activated')->default(false);
            $table->decimal('fuel_used', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outages');
    }
};
