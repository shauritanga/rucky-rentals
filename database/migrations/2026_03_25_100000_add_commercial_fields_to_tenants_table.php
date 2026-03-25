<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tenant_type', 20)->default('individual')->after('phone');
            $table->string('company_name')->nullable()->after('tenant_type');
            $table->string('registration_number')->nullable()->after('company_name');
            $table->string('tin', 50)->nullable()->after('registration_number');
            $table->string('vrn', 50)->nullable()->after('tin');
            $table->string('contact_person')->nullable()->after('vrn');
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
