<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;

class LeaseSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['unit'=>'A-101','tenant'=>'Sarah Rutto',    'start'=>'2024-01-01','end'=>'2025-01-01','months'=>12,'cycle'=>3, 'rent'=>1200,'deposit'=>2400,'status'=>'active'],
            ['unit'=>'A-102','tenant'=>'Brian Kimani',   'start'=>'2024-03-01','end'=>'2025-03-01','months'=>12,'cycle'=>3, 'rent'=>950, 'deposit'=>1900,'status'=>'overdue'],
            ['unit'=>'A-103','tenant'=>'Charles Kiprop', 'start'=>'2024-06-01','end'=>'2025-06-01','months'=>12,'cycle'=>4, 'rent'=>750, 'deposit'=>1500,'status'=>'active'],
            ['unit'=>'A-104','tenant'=>'Lydia Wambui',   'start'=>'2024-02-01','end'=>'2025-02-01','months'=>12,'cycle'=>6, 'rent'=>1450,'deposit'=>2900,'status'=>'active'],
            ['unit'=>'A-105','tenant'=>'Peter Otieno',   'start'=>'2024-04-01','end'=>'2025-04-01','months'=>12,'cycle'=>4, 'rent'=>920, 'deposit'=>1840,'status'=>'active'],
            ['unit'=>'A-106','tenant'=>'Nancy Mwende',   'start'=>'2024-07-01','end'=>'2025-07-01','months'=>12,'cycle'=>12,'rent'=>720, 'deposit'=>1440,'status'=>'active'],
            ['unit'=>'B-201','tenant'=>'Amina Musa',     'start'=>'2024-05-01','end'=>'2025-05-01','months'=>12,'cycle'=>6, 'rent'=>1400,'deposit'=>2800,'status'=>'active'],
            ['unit'=>'B-203','tenant'=>'David Kamau',    'start'=>'2024-08-01','end'=>'2025-08-01','months'=>12,'cycle'=>3, 'rent'=>780, 'deposit'=>1560,'status'=>'active'],
            ['unit'=>'B-204','tenant'=>'Rose Njeri',     'start'=>'2024-09-01','end'=>'2025-09-01','months'=>12,'cycle'=>6, 'rent'=>1050,'deposit'=>2100,'status'=>'active'],
            ['unit'=>'B-205','tenant'=>'Moses Odhiambo', 'start'=>'2024-10-01','end'=>'2025-10-01','months'=>12,'cycle'=>4, 'rent'=>1380,'deposit'=>2760,'status'=>'active'],
            ['unit'=>'C-301','tenant'=>'James Omondi',   'start'=>'2023-11-01','end'=>'2024-11-01','months'=>12,'cycle'=>3, 'rent'=>1100,'deposit'=>2200,'status'=>'overdue'],
            ['unit'=>'C-302','tenant'=>'Fatima Ngugi',   'start'=>'2023-12-01','end'=>'2024-12-01','months'=>12,'cycle'=>12,'rent'=>1300,'deposit'=>2600,'status'=>'expiring'],
            ['unit'=>'C-303','tenant'=>'Sarah Rutto',    'start'=>'2025-01-01','end'=>'2026-01-01','months'=>12,'cycle'=>3, 'rent'=>760, 'deposit'=>1520,'status'=>'active'],
            ['unit'=>'C-304','tenant'=>'Brian Kimani',   'start'=>'2025-02-01','end'=>'2026-02-01','months'=>12,'cycle'=>3, 'rent'=>1000,'deposit'=>2000,'status'=>'active'],
            ['unit'=>'C-305','tenant'=>'Amina Musa',     'start'=>'2025-03-01','end'=>'2026-03-01','months'=>12,'cycle'=>6, 'rent'=>1800,'deposit'=>3600,'status'=>'expiring'],
            ['unit'=>'D-402','tenant'=>'Charles Kiprop', 'start'=>'2025-04-01','end'=>'2026-04-01','months'=>12,'cycle'=>4, 'rent'=>1600,'deposit'=>3200,'status'=>'active'],
            ['unit'=>'D-403','tenant'=>'Lydia Wambui',   'start'=>'2025-05-01','end'=>'2026-05-01','months'=>12,'cycle'=>6, 'rent'=>820, 'deposit'=>1640,'status'=>'active'],
            ['unit'=>'E-501','tenant'=>'Peter Otieno',   'start'=>'2025-06-01','end'=>'2026-06-01','months'=>12,'cycle'=>4, 'rent'=>1650,'deposit'=>3300,'status'=>'active'],
            ['unit'=>'E-502','tenant'=>'Nancy Mwende',   'start'=>'2025-07-01','end'=>'2026-07-01','months'=>12,'cycle'=>12,'rent'=>2000,'deposit'=>4000,'status'=>'active'],
            ['unit'=>'E-503','tenant'=>'David Kamau',    'start'=>'2025-08-01','end'=>'2026-08-01','months'=>12,'cycle'=>3, 'rent'=>1150,'deposit'=>2300,'status'=>'active'],
            ['unit'=>'E-504','tenant'=>'Rose Njeri',     'start'=>'2025-09-01','end'=>'2026-09-01','months'=>12,'cycle'=>6, 'rent'=>800, 'deposit'=>1600,'status'=>'active'],
            ['unit'=>'E-505','tenant'=>'Moses Odhiambo', 'start'=>'2025-10-01','end'=>'2026-10-01','months'=>12,'cycle'=>4, 'rent'=>1700,'deposit'=>3400,'status'=>'active'],
            ['unit'=>'F-601','tenant'=>'Charles Kiprop', 'start'=>'2025-01-01','end'=>'2027-01-01','months'=>24,'cycle'=>4, 'rent'=>4500,'deposit'=>9000,'status'=>'active'],
            ['unit'=>'F-602','tenant'=>'Lydia Wambui',   'start'=>'2025-02-01','end'=>'2027-02-01','months'=>24,'cycle'=>6, 'rent'=>2400,'deposit'=>4800,'status'=>'active'],
            ['unit'=>'F-605','tenant'=>'David Kamau',    'start'=>'2025-05-01','end'=>'2027-05-01','months'=>24,'cycle'=>3, 'rent'=>4200,'deposit'=>8400,'status'=>'active'],
        ];

        foreach ($data as $d) {
            $tenant = Tenant::where('name', $d['tenant'])->first();
            $unit   = Unit::where('unit_number', $d['unit'])->first();
            if (!$tenant || !$unit) continue;

            Lease::create([
                'tenant_id'       => $tenant->id,
                'unit_id'         => $unit->id,
                'start_date'      => $d['start'],
                'end_date'        => $d['end'],
                'duration_months' => $d['months'],
                'payment_cycle'   => $d['cycle'],
                'monthly_rent'    => $d['rent'],
                'deposit'         => $d['deposit'],
                'status'          => $d['status'],
                'terms'           => "Minimum lease duration: {$d['months']} months. Payment cycle: every {$d['cycle']} month(s). Late payment penalty: 5% after 7-day grace period. Tenant responsible for utility bills. No subletting without written consent.",
                'approval_log'    => json_encode([
                    ['step'=>0,'action'=>'submitted','by'=>'James Mwangi (Lease Manager)','date'=>$d['start'],'text'=>"Lease submitted for Unit {$d['unit']}."],
                    ['step'=>1,'action'=>'approved','by'=>'Diana Ochieng (Accountant)','date'=>$d['start'],'text'=>'Financials verified.'],
                    ['step'=>2,'action'=>'approved','by'=>'James Mwangi (Property Manager)','date'=>$d['start'],'text'=>'Final approval. Lease activated.'],
                ]),
            ]);
        }
    }
}
