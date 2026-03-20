<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MeterReading;
use App\Models\Unit;

class MeterReadingSeeder extends Seeder
{
    public function run(): void
    {
        $readings = [
            ['unit'=>'A-101','prev'=>12450,'curr'=>12906,'gen'=>12],
            ['unit'=>'A-102','prev'=>8320, 'curr'=>8701, 'gen'=>9],
            ['unit'=>'A-103','prev'=>6100, 'curr'=>6418, 'gen'=>8],
            ['unit'=>'A-104','prev'=>15200,'curr'=>15698,'gen'=>14],
            ['unit'=>'A-105','prev'=>9800, 'curr'=>10178,'gen'=>10],
            ['unit'=>'A-106','prev'=>5400, 'curr'=>5718, 'gen'=>7],
            ['unit'=>'B-201','prev'=>18600,'curr'=>19248,'gen'=>18],
            ['unit'=>'B-203','prev'=>7200, 'curr'=>7560, 'gen'=>9],
            ['unit'=>'B-204','prev'=>10400,'curr'=>10802,'gen'=>11],
            ['unit'=>'B-205','prev'=>14800,'curr'=>15318,'gen'=>14],
            ['unit'=>'C-301','prev'=>11200,'curr'=>11648,'gen'=>12],
            ['unit'=>'C-302','prev'=>13600,'curr'=>14072,'gen'=>13],
            ['unit'=>'C-303','prev'=>5800, 'curr'=>6062, 'gen'=>7],
            ['unit'=>'C-304','prev'=>8900, 'curr'=>9262, 'gen'=>10],
            ['unit'=>'C-305','prev'=>19800,'curr'=>20598,'gen'=>22],
            ['unit'=>'D-402','prev'=>16200,'curr'=>16848,'gen'=>18],
            ['unit'=>'D-403','prev'=>6200, 'curr'=>6510, 'gen'=>8],
            ['unit'=>'E-501','prev'=>18400,'curr'=>19136,'gen'=>20],
            ['unit'=>'E-502','prev'=>24000,'curr'=>24840,'gen'=>23],
            ['unit'=>'E-503','prev'=>9800, 'curr'=>10192,'gen'=>11],
            ['unit'=>'E-504','prev'=>7600, 'curr'=>7942, 'gen'=>9],
            ['unit'=>'E-505','prev'=>21000,'curr'=>21882,'gen'=>24],
            ['unit'=>'F-601','prev'=>42000,'curr'=>43512,'gen'=>42],
            ['unit'=>'F-602','prev'=>28000,'curr'=>29008,'gen'=>28],
            ['unit'=>'F-605','prev'=>39000,'curr'=>40404,'gen'=>39],
        ];

        foreach ($readings as $r) {
            $unit = Unit::where('unit_number', $r['unit'])->first();
            if (!$unit) continue;
            MeterReading::create([
                'unit_id'      => $unit->id,
                'month'        => '2026-03',
                'prev_reading' => $r['prev'],
                'curr_reading' => $r['curr'],
                'gen_kwh'      => $r['gen'],
                'reading_date' => '2026-03-19',
            ]);
        }
    }
}
