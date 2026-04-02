<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CleanSlateSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. Wipe all data tables using TRUNCATE ... CASCADE (PostgreSQL) ---
        // CASCADE automatically handles FK dependencies without needing to order tables.
        $tables = implode(', ', [
            'journal_lines',
            'accounting_events',
            'journal_entries',
            'audit_logs',
            'notifications',
            'lease_installments',
            'invoice_items',
            'invoices',
            'payments',
            'electricity_sales',
            'meter_readings',
            'outages',
            'fuel_logs',
            'scheduled_maintenance',
            'maintenance_tickets',
            'documents',
            'leases',
            'units',
            'tenants',
            'users',
            'accounts',
            'properties',
        ]);

        DB::statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");

        // --- 2. Create superuser ---
        User::create([
            'name'                => 'Super Admin',
            'email'               => 'admin@rukyrentals.co.tz',
            'password'            => Hash::make('admin123'),
            'role'                => 'superuser',
            'property_id'         => null,
            'email_verified_at'   => now(),
            'status'              => 'active',
            'must_change_password' => false,
        ]);

        // Accounts are auto-created per-property by AccountingAutoPoster when GL entries
        // are posted. Seeding null-property accounts causes duplicate codes in the CoA
        // and pollutes P&L/Balance Sheet reports. Let AutoPoster handle account creation.
    }
}
