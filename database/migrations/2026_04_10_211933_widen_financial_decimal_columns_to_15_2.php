<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen all financial decimal columns from (10,2) to (15,2).
 *
 * decimal(10,2) overflows above ~99.9 million TZS.
 * decimal(15,2) supports up to ~999 billion TZS — sufficient for any property value.
 * journal_lines already uses (15,2); this brings all other tables into alignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // invoice_items — the column that triggered the overflow error
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('total', 15, 2)->change();
        });

        // leases
        Schema::table('leases', function (Blueprint $table) {
            $table->decimal('monthly_rent', 15, 2)->change();
            $table->decimal('deposit', 15, 2)->change();
        });

        // payments
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        // units
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('rent', 15, 2)->change();
            $table->decimal('deposit', 15, 2)->default(0)->change();
        });

        // maintenance_records / maintenance_tickets
        $maintenanceTable = Schema::hasTable('maintenance_records') ? 'maintenance_records' : 'maintenance_tickets';
        Schema::table($maintenanceTable, function (Blueprint $table) {
            $table->decimal('cost', 15, 2)->nullable()->change();
        });

        // fuel_logs
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('total_cost', 15, 2)->change();
        });

        // invoices — total_in_base and amount_in_base if present
        if (Schema::hasColumn('invoices', 'total_in_base')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('total_in_base', 15, 2)->nullable()->change();
            });
        }

        // payments — amount_in_base
        if (Schema::hasColumn('payments', 'amount_in_base')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('amount_in_base', 15, 2)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total', 10, 2)->change();
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->decimal('monthly_rent', 10, 2)->change();
            $table->decimal('deposit', 10, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->decimal('rent', 10, 2)->change();
            $table->decimal('deposit', 10, 2)->default(0)->change();
        });

        $maintenanceTable = Schema::hasTable('maintenance_records') ? 'maintenance_records' : 'maintenance_tickets';
        Schema::table($maintenanceTable, function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->change();
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 2)->change();
        });
    }
};
