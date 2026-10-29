<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('breakdown_rent', 14, 2)->default(0);
            $table->decimal('breakdown_service_charge', 14, 2)->default(0);
            $table->decimal('breakdown_electricity', 14, 2)->default(0);
            $table->boolean('issue_receipt')->default(false);
            $table->boolean('wht_confirmed')->default(false);
            $table->string('wht_reference')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
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
