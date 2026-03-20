<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Outage;

class OutageSeeder extends Seeder
{
    public function run(): void
    {
        $outages = [
            ['date'=>'2026-03-04','start'=>'14:30','end'=>'18:45','type'=>'major','floors'=>'All floors','gen'=>true, 'fuel'=>25,'notes'=>'KPLC scheduled maintenance #KPL-24891'],
            ['date'=>'2026-03-11','start'=>'22:10','end'=>'23:50','type'=>'minor','floors'=>'Floor 1-3', 'gen'=>true, 'fuel'=>11,'notes'=>'Transformer fault — restored by KPLC'],
            ['date'=>'2026-03-17','start'=>'09:00','end'=>'17:00','type'=>'major','floors'=>'All floors','gen'=>true, 'fuel'=>51,'notes'=>'Load shedding — County-wide outage'],
        ];

        foreach ($outages as $o) {
            Outage::create([
                'outage_date'         => $o['date'],
                'start_time'          => $o['start'],
                'end_time'            => $o['end'],
                'type'                => $o['type'],
                'floors_affected'     => $o['floors'],
                'generator_activated' => $o['gen'],
                'fuel_used'           => $o['fuel'],
                'notes'               => $o['notes'],
            ]);
        }
    }
}
