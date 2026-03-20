<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FuelLog;

class FuelLogSeeder extends Seeder
{
    public function run(): void
    {
        FuelLog::create(['log_date'=>'2026-03-01','litres'=>80,'price_per_litre'=>185,'total_cost'=>14800,'supplier'=>'Total Energies','level_after'=>80,'recorded_by'=>'James Mwangi']);
        FuelLog::create(['log_date'=>'2026-03-17','litres'=>30,'price_per_litre'=>185,'total_cost'=>5550, 'supplier'=>'Shell Kenya',   'level_after'=>68,'recorded_by'=>'James Mwangi']);
    }
}
