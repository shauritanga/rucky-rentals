<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('breakdown_rent', 14, 2)->default(0)->after('amount_in_base');
            $table->decimal('breakdown_service_charge', 14, 2)->default(0)->after('breakdown_rent');
            $table->decimal('breakdown_electricity', 14, 2)->default(0)->after('breakdown_service_charge');
            $table->boolean('issue_receipt')->default(false)->after('breakdown_electricity');
            $table->boolean('wht_confirmed')->default(false)->after('issue_receipt');
            $table->string('wht_reference')->nullable()->after('wht_confirmed');
            $table->foreignId('receipt_id')->nullable()->after('wht_reference')->constrained('receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
            $table->dropColumn([
                'breakdown_rent',
                'breakdown_service_charge',
                'breakdown_electricity',
                'issue_receipt',
                'wht_confirmed',
                'wht_reference',
            ]);
        });
    }
};
