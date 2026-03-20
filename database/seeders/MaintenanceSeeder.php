<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaintenanceTicket;
use App\Models\Unit;

class MaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $tickets = [
            ['number'=>'TK-001','title'=>'Broken water pipe',        'unit'=>'D-401', 'cat'=>'Plumbing',   'priority'=>'high','status'=>'open',        'reported'=>'2026-03-17','assignee'=>'Peter Ng.',    'cost'=>null, 'desc'=>'Tenant reported flooding in bathroom. Water main shut off. Urgent repair needed.'],
            ['number'=>'TK-002','title'=>'Flickering ceiling light', 'unit'=>'B-204', 'cat'=>'Electrical', 'priority'=>'med', 'status'=>'in-progress', 'reported'=>'2026-03-15','assignee'=>'JK Electric',  'cost'=>180,  'desc'=>'Ceiling light in living room flickers intermittently. Possible faulty wiring.'],
            ['number'=>'TK-003','title'=>'Broken door hinge',        'unit'=>'A-105', 'cat'=>'General',    'priority'=>'low', 'status'=>'open',        'reported'=>'2026-03-12','assignee'=>null,            'cost'=>null, 'desc'=>'Front door hinge is loose. Door does not close fully.'],
            ['number'=>'TK-004','title'=>'AC unit not cooling',      'unit'=>'C-303', 'cat'=>'HVAC',       'priority'=>'med', 'status'=>'in-progress', 'reported'=>'2026-03-10','assignee'=>'Cool Air Ltd', 'cost'=>350,  'desc'=>'AC unit runs but blows warm air. Likely refrigerant issue.'],
            ['number'=>'TK-005','title'=>'Faulty door lock',         'unit'=>'E-502', 'cat'=>'Security',   'priority'=>'high','status'=>'open',        'reported'=>'2026-03-09','assignee'=>null,            'cost'=>null, 'desc'=>'Main door lock is jammed. Tenant cannot lock apartment properly.'],
            ['number'=>'TK-006','title'=>'Shower drain blocked',     'unit'=>'A-103', 'cat'=>'Plumbing',   'priority'=>'med', 'status'=>'resolved',    'reported'=>'2026-03-05','assignee'=>'Peter Ng.',    'cost'=>90,   'desc'=>'Shower drains very slowly.'],
            ['number'=>'TK-007','title'=>'Power socket sparking',    'unit'=>'F-601', 'cat'=>'Electrical', 'priority'=>'high','status'=>'resolved',    'reported'=>'2026-03-03','assignee'=>'JK Electric',  'cost'=>220,  'desc'=>'Kitchen socket sparked when plugging in appliance.'],
            ['number'=>'TK-008','title'=>'Window latch broken',      'unit'=>'C-305', 'cat'=>'General',    'priority'=>'low', 'status'=>'resolved',    'reported'=>'2026-02-28','assignee'=>'In-house',     'cost'=>40,   'desc'=>'Window latch is broken, window cannot be locked.'],
            ['number'=>'TK-009','title'=>'Leaking tap — kitchen',    'unit'=>'B-201', 'cat'=>'Plumbing',   'priority'=>'low', 'status'=>'resolved',    'reported'=>'2026-02-25','assignee'=>'Peter Ng.',    'cost'=>60,   'desc'=>'Kitchen tap drips continuously.'],
            ['number'=>'TK-010','title'=>'Heating not working',      'unit'=>'D-402', 'cat'=>'HVAC',       'priority'=>'med', 'status'=>'resolved',    'reported'=>'2026-02-20','assignee'=>'Cool Air Ltd', 'cost'=>280,  'desc'=>'Heater does not turn on.'],
        ];

        foreach ($tickets as $t) {
            $unit = Unit::where('unit_number', $t['unit'])->first();
            MaintenanceTicket::create([
                'ticket_number' => $t['number'],
                'title'         => $t['title'],
                'description'   => $t['desc'],
                'unit_ref'      => $t['unit'],
                'unit_id'       => $unit?->id,
                'category'      => $t['cat'],
                'priority'      => $t['priority'],
                'status'        => $t['status'],
                'assignee'      => $t['assignee'],
                'cost'          => $t['cost'],
                'reported_date' => $t['reported'],
                'notes'         => json_encode([]),
            ]);
        }
    }
}
