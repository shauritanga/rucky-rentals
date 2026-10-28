<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('invoice_code', 12)->nullable()->unique()->after('initials');
        });

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            $tenant->invoice_code = Tenant::generateInvoiceCode($tenant->company_name ?: (string) $tenant->name, $tenant->id);
            $tenant->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['invoice_code']);
            $table->dropColumn('invoice_code');
        });
    }
};
