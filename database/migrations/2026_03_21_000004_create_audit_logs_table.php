<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable();
            $table->string('action', 180);
            $table->string('resource', 180)->nullable();
            $table->string('property_name', 160)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('result', 30)->default('success');
            $table->string('category', 40)->default('settings');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['category', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
