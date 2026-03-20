<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            UnitSeeder::class,
            LeaseSeeder::class,
            PaymentSeeder::class,
            MaintenanceSeeder::class,
            OutageSeeder::class,
            FuelLogSeeder::class,
            MeterReadingSeeder::class,
            AccountSeeder::class,
            JournalEntrySeeder::class,
        ]);
    }
}
