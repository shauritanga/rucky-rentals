<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Default management fee rate: 0 means no deduction until explicitly configured.
        SystemSetting::firstOrCreate(
            ['key' => 'management_fee_rate'],
            ['value' => '0'],
        );
    }

    public function down(): void
    {
        SystemSetting::where('key', 'management_fee_rate')->delete();
    }
};
