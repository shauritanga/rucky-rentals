<?php

use App\Models\Account;
use App\Models\Property;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $newAccounts = [
            ['code' => '1120', 'name' => 'WHT Tax Credit',        'type' => 'asset',     'category' => 'Current Assets',      'balance' => 0, 'ytd_activity' => 0],
            ['code' => '4100', 'name' => 'Service Charge Income',  'type' => 'revenue',   'category' => 'Operating Revenue',   'balance' => 0, 'ytd_activity' => 0],
            ['code' => '5500', 'name' => 'Management Fee',         'type' => 'expense',   'category' => 'Operating Expenses',  'balance' => 0, 'ytd_activity' => 0],
            ['code' => '2500', 'name' => 'Management Fee Payable', 'type' => 'liability', 'category' => 'Current Liabilities', 'balance' => 0, 'ytd_activity' => 0],
        ];

        foreach (Property::pluck('id') as $propertyId) {
            foreach ($newAccounts as $account) {
                Account::updateOrCreate(
                    ['property_id' => $propertyId, 'code' => $account['code']],
                    $account,
                );
            }
        }

        // Default management fee rate — 0 means no deduction until explicitly configured
        SystemSetting::firstOrCreate(
            ['key' => 'management_fee_rate'],
            ['value' => '0'],
        );
    }

    public function down(): void
    {
        $codes = ['1120', '4100', '5500', '2500'];
        Account::whereIn('code', $codes)->delete();
        SystemSetting::where('key', 'management_fee_rate')->delete();
    }
};
