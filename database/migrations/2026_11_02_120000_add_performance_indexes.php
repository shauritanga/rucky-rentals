<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => [index name => columns]. Names are short (MySQL allows 64 characters). */
    private const INDEXES = [
        'invoices' => [
            'idx_invoices_property_created' => ['property_id', 'created_at'],
            'idx_invoices_lease' => ['lease_id'],
            'idx_invoices_status_due' => ['status', 'due_date'],
        ],
        'invoice_items' => ['idx_invoice_items_invoice' => ['invoice_id']],
        'payments' => [
            'idx_payments_invoice' => ['invoice_id'],
            'idx_payments_property' => ['property_id', 'status'],
            'idx_payments_tenant' => ['tenant_id'],
        ],
        'leases' => [
            'idx_leases_unit_status' => ['unit_id', 'status'],
            'idx_leases_property_status' => ['property_id', 'status'],
            'idx_leases_tenant' => ['tenant_id'],
        ],
        'lease_installments' => ['idx_installments_invoice' => ['invoice_id']],
        'tenants' => ['idx_tenants_property' => ['property_id']],
        'maintenance_tickets' => ['idx_tickets_property' => ['property_id']],
        'meter_readings' => ['idx_readings_unit' => ['unit_id']],
        'audit_logs' => ['idx_audit_property' => ['property_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if ($this->hasAllColumns($table, $columns) && ! Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasAllColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
