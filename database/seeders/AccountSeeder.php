<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Property;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $propertyId = Property::query()->value('id');

        $accounts = [
            ['code' => '1000', 'name' => 'Cash at Bank',        'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 248600, 'ytd' => 84200],
            ['code' => '1010', 'name' => 'Petty Cash',           'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 3500,   'ytd' => 500],
            ['code' => '1100', 'name' => 'Rent Receivable',      'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 6400,   'ytd' => 42000],
            ['code' => '1110', 'name' => 'Security Deposits',    'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 52800,  'ytd' => 3200],
            ['code' => '1200', 'name' => 'Prepaid Expenses',     'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 4800,   'ytd' => -1200],
            ['code' => '1500', 'name' => 'Building & Property',  'type' => 'asset',    'cat' => 'Fixed Assets',         'balance' => 4800000, 'ytd' => 0],
            ['code' => '1510', 'name' => 'Furniture & Fittings', 'type' => 'asset',    'cat' => 'Fixed Assets',         'balance' => 48000,  'ytd' => 6000],
            ['code' => '1520', 'name' => 'Equipment',            'type' => 'asset',    'cat' => 'Fixed Assets',         'balance' => 22000,  'ytd' => 0],
            ['code' => '1600', 'name' => 'Accum. Depreciation',  'type' => 'contra',   'cat' => 'Fixed Assets',         'balance' => -185000, 'ytd' => -18000],
            ['code' => '2000', 'name' => 'Accounts Payable',     'type' => 'liability', 'cat' => 'Current Liabilities',  'balance' => 12400,  'ytd' => 8200],
            ['code' => '2100', 'name' => 'Deposits Payable',     'type' => 'liability', 'cat' => 'Current Liabilities',  'balance' => 52800,  'ytd' => 3200],
            ['code' => '2200', 'name' => 'VAT Payable',          'type' => 'liability', 'cat' => 'Current Liabilities',  'balance' => 0,      'ytd' => 0],
            ['code' => '2300', 'name' => 'Accrued Expenses',     'type' => 'liability', 'cat' => 'Current Liabilities',  'balance' => 8600,   'ytd' => 2400],
            ['code' => '2510', 'name' => 'Deferred Rent',        'type' => 'liability', 'cat' => 'Long-Term Liabilities', 'balance' => 18000,  'ytd' => -6000],
            ['code' => '3000', 'name' => "Owner's Capital",      'type' => 'equity',   'cat' => 'Equity',               'balance' => 3600000, 'ytd' => 0],
            ['code' => '3100', 'name' => 'Retained Earnings',    'type' => 'equity',   'cat' => 'Equity',               'balance' => 115600, 'ytd' => 0],
            ['code' => '4000', 'name' => 'Rental Income',        'type' => 'revenue',  'cat' => 'Revenue',              'balance' => 126000, 'ytd' => 126000],
            ['code' => '4200', 'name' => 'Other Income',         'type' => 'revenue',  'cat' => 'Revenue',              'balance' => 2400,   'ytd' => 2400],
            ['code' => '5000', 'name' => 'Maintenance Expense',  'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 8400,   'ytd' => 8400],
            ['code' => '5100', 'name' => 'Utilities Expense',    'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 4020,   'ytd' => 4020],
            ['code' => '5200', 'name' => 'Insurance Expense',    'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 2520,   'ytd' => 2520],
            ['code' => '5300', 'name' => 'Depreciation Expense', 'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 5400,   'ytd' => 5400],
            ['code' => '5400', 'name' => 'Admin & Office',       'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 840,    'ytd' => 840],
            // Owner revenue waterfall accounts
            ['code' => '1120', 'name' => 'WHT Tax Credit',        'type' => 'asset',    'cat' => 'Current Assets',       'balance' => 0,      'ytd' => 0],
            ['code' => '4100', 'name' => 'Service Charge Income',  'type' => 'revenue',  'cat' => 'Operating Revenue',    'balance' => 0,      'ytd' => 0],
            ['code' => '5500', 'name' => 'Management Fee',         'type' => 'expense',  'cat' => 'Operating Expenses',   'balance' => 0,      'ytd' => 0],
            ['code' => '2500', 'name' => 'Management Fee Payable', 'type' => 'liability','cat' => 'Current Liabilities',  'balance' => 0,      'ytd' => 0],
        ];

        foreach ($accounts as $a) {
            Account::updateOrCreate(
                ['property_id' => $propertyId, 'code' => $a['code']],
                [
                    'name'         => $a['name'],
                    'type'         => $a['type'],
                    'category'     => $a['cat'],
                    'balance'      => $a['balance'],
                    'ytd_activity' => $a['ytd'],
                ]
            );
        }
    }
}
