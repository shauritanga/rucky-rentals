<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the rental data (units, tenants, leases, invoices, payments and everything attached to them) and keeps
 * properties, users, settings, saved report templates, the audit trail and notifications.
 *
 * Meant for starting a system over before real data is entered. It cannot be undone, so it takes a database backup
 * first (unless told not to) and asks for a typed confirmation.
 */
class ClearRentalData extends Command
{
    protected $signature = 'rentals:clear-data {--skip-backup : Do not take a backup first (not recommended)}';
    protected $description = 'Delete all units, tenants, leases, invoices, payments and their attached records. Keeps properties, users, settings and the audit trail.';

    private const PHRASE = 'DELETE ALL RENTAL DATA';

    /** Children before parents, so no foreign key is ever violated. */
    private const TABLES = [
        'payment_change_requests',
        'lease_change_requests',
        'tenant_credits',
        'receipts',
        'payments',
        'invoice_items',
        'lease_installments',
        'invoices',
        'unit_clearance_items',
        'unit_clearances',
        'electricity_sales',
        'meter_readings',
        'scheduled_maintenance',
        'maintenance_tickets',
        'leases',
        'units',
        'tenants',
    ];

    /** Documents that belong to a unit, tenant, invoice, ticket or clearance (general documents stay). */
    private function linkedDocuments()
    {
        return DB::table('documents')->where(function ($q) {
            $q->whereNotNull('unit_id')->orWhereNotNull('tenant_id')->orWhereNotNull('invoice_id')
                ->orWhereNotNull('maintenance_record_id')->orWhereNotNull('unit_clearance_id')->orWhereNotNull('unit_clearance_item_id');
        });
    }

    public function handle(): int
    {
        $tables = array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
        $hasDocuments = Schema::hasTable('documents');

        $this->warn('This permanently deletes rental data on database "' . DB::connection()->getDatabaseName() . '" (' . config('app.url') . ').');
        $this->line('Rows that will be deleted:');
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) DB::table($table)->count();
            $this->line(sprintf('  %-26s %s', $table, number_format($counts[$table])));
        }
        if ($hasDocuments) {
            $counts['documents (tied to units, tenants or invoices)'] = (int) $this->linkedDocuments()->count();
            $this->line(sprintf('  %-26s %s', 'documents (linked)', number_format($counts['documents (tied to units, tenants or invoices)'])));
        }
        $this->line('Kept: properties, users, system settings, report templates, audit trail, notifications, general documents.');

        if (!$this->option('skip-backup')) {
            $this->info('Taking a backup first...');
            if ($this->call('backup:database') !== self::SUCCESS) {
                $this->error('The backup failed, so nothing was deleted.');

                return self::FAILURE;
            }
        }

        $typed = $this->ask('Type ' . self::PHRASE . ' to continue');
        if ($typed !== self::PHRASE) {
            $this->error('Confirmation did not match. Nothing was deleted.');

            return self::FAILURE;
        }

        // Files of the documents about to go.
        $files = $hasDocuments ? $this->linkedDocuments()->whereNotNull('file_path')->pluck('file_path')->all() : [];

        DB::transaction(function () use ($tables, $hasDocuments) {
            if ($hasDocuments) {
                $this->linkedDocuments()->delete();
            }
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
            if (Schema::hasTable('properties')) {
                DB::table('properties')->update(['unit_count' => 0, 'occupied_units' => 0]);
            }
        });

        foreach ($files as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable) {
                // a missing file is not a reason to stop
            }
        }

        $this->info('Done. Rental data deleted: ' . number_format(array_sum($counts)) . ' rows and ' . count($files) . ' document files.');

        return self::SUCCESS;
    }
}
