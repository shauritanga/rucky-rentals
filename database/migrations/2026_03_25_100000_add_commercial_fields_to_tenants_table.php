<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tenant_type', 20)->default('individual');
            $table->string('company_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tin', 50)->nullable();
            $table->string('vrn', 50)->nullable();
            $table->string('contact_person')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_type',
                'company_name',
                'registration_number',
                'tin',
                'vrn',
                'contact_person',
            ]);
        });
    }
};
