<?php

namespace Database\Seeders;

use App\Models\Account;
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
            'email'               => 'admin@ruckyrentals.co.tz',
            'password'            => Hash::make('admin123'),
            'role'                => 'superuser',
            'property_id'         => null,
            'email_verified_at'   => now(),
            'status'              => 'active',
            'must_change_password' => false,
        ]);

        // --- 3. Seed chart of accounts (property_id = null, all balances at 0) ---
        $accounts = [
            // Current Assets
            ['code' => '1000', 'name' => 'Cash at Bank',           'type' => 'asset',     'category' => 'Current Assets'],
            ['code' => '1010', 'name' => 'Petty Cash',              'type' => 'asset',     'category' => 'Current Assets'],
            ['code' => '1100', 'name' => 'Rent Receivable',         'type' => 'asset',     'category' => 'Current Assets'],
            ['code' => '1110', 'name' => 'Security Deposits',       'type' => 'asset',     'category' => 'Current Assets'],
            ['code' => '1120', 'name' => 'WHT Tax Credit',          'type' => 'asset',     'category' => 'Current Assets'],
            ['code' => '1200', 'name' => 'Prepaid Expenses',        'type' => 'asset',     'category' => 'Current Assets'],
            // Fixed Assets
            ['code' => '1500', 'name' => 'Building & Property',     'type' => 'asset',     'category' => 'Fixed Assets'],
            ['code' => '1510', 'name' => 'Furniture & Fittings',    'type' => 'asset',     'category' => 'Fixed Assets'],
            ['code' => '1520', 'name' => 'Equipment',               'type' => 'asset',     'category' => 'Fixed Assets'],
            ['code' => '1600', 'name' => 'Accum. Depreciation',     'type' => 'contra',    'category' => 'Fixed Assets'],
            // Current Liabilities
            ['code' => '2000', 'name' => 'Accounts Payable',        'type' => 'liability', 'category' => 'Current Liabilities'],
            ['code' => '2100', 'name' => 'Deposits Payable',        'type' => 'liability', 'category' => 'Current Liabilities'],
            ['code' => '2200', 'name' => 'VAT Payable',             'type' => 'liability', 'category' => 'Current Liabilities'],
            ['code' => '2300', 'name' => 'Accrued Expenses',        'type' => 'liability', 'category' => 'Current Liabilities'],
            ['code' => '2500', 'name' => 'Management Fee Payable',  'type' => 'liability', 'category' => 'Current Liabilities'],
            // Long-Term Liabilities
            ['code' => '2510', 'name' => 'Deferred Rent',           'type' => 'liability', 'category' => 'Long-Term Liabilities'],
            // Equity
            ['code' => '3000', 'name' => "Owner's Capital",         'type' => 'equity',    'category' => 'Equity'],
            ['code' => '3100', 'name' => 'Retained Earnings',       'type' => 'equity',    'category' => 'Equity'],
            // Revenue
            ['code' => '4000', 'name' => 'Rental Income',           'type' => 'revenue',   'category' => 'Operating Revenue'],
            ['code' => '4100', 'name' => 'Service Charge Income',   'type' => 'revenue',   'category' => 'Operating Revenue'],
            ['code' => '4200', 'name' => 'Other Income',            'type' => 'revenue',   'category' => 'Revenue'],
            // Operating Expenses
            ['code' => '5000', 'name' => 'Maintenance Expense',     'type' => 'expense',   'category' => 'Operating Expenses'],
            ['code' => '5100', 'name' => 'Utilities Expense',       'type' => 'expense',   'category' => 'Operating Expenses'],
            ['code' => '5200', 'name' => 'Insurance Expense',       'type' => 'expense',   'category' => 'Operating Expenses'],
            ['code' => '5300', 'name' => 'Depreciation Expense',    'type' => 'expense',   'category' => 'Operating Expenses'],
            ['code' => '5400', 'name' => 'Admin & Office',          'type' => 'expense',   'category' => 'Operating Expenses'],
            ['code' => '5500', 'name' => 'Management Fee',          'type' => 'expense',   'category' => 'Operating Expenses'],
        ];

        foreach ($accounts as $a) {
            Account::create([
                'property_id'  => null,
                'code'         => $a['code'],
                'name'         => $a['name'],
                'type'         => $a['type'],
                'category'     => $a['category'],
                'balance'      => 0,
                'ytd_activity' => 0,
            ]);
        }
    }
}
