<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['tenant'=>'Sarah Rutto',    'unit'=>'A-101','month'=>'Mar 2026','amount'=>1200,'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-03-01'],
            ['tenant'=>'Brian Kimani',   'unit'=>'A-102','month'=>'Mar 2026','amount'=>950, 'method'=>null,            'status'=>'overdue', 'date'=>null],
            ['tenant'=>'Amina Musa',     'unit'=>'B-201','month'=>'Mar 2026','amount'=>1400,'method'=>'Bank Transfer', 'status'=>'paid',    'date'=>'2026-03-01'],
            ['tenant'=>'James Omondi',   'unit'=>'C-301','month'=>'Mar 2026','amount'=>1100,'method'=>null,            'status'=>'overdue', 'date'=>null],
            ['tenant'=>'Fatima Ngugi',   'unit'=>'C-302','month'=>'Mar 2026','amount'=>1300,'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-03-02'],
            ['tenant'=>'Charles Kiprop', 'unit'=>'A-103','month'=>'Mar 2026','amount'=>750, 'method'=>'Cash',          'status'=>'paid',    'date'=>'2026-03-03'],
            ['tenant'=>'Lydia Wambui',   'unit'=>'A-104','month'=>'Mar 2026','amount'=>1450,'method'=>'Bank Transfer', 'status'=>'paid',    'date'=>'2026-03-01'],
            ['tenant'=>'Peter Otieno',   'unit'=>'A-105','month'=>'Mar 2026','amount'=>920, 'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-03-02'],
            ['tenant'=>'Nancy Mwende',   'unit'=>'A-106','month'=>'Mar 2026','amount'=>720, 'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-03-01'],
            ['tenant'=>'David Kamau',    'unit'=>'B-203','month'=>'Mar 2026','amount'=>780, 'method'=>'Cash',          'status'=>'pending', 'date'=>null],
            ['tenant'=>'Rose Njeri',     'unit'=>'B-204','month'=>'Mar 2026','amount'=>1050,'method'=>null,            'status'=>'pending', 'date'=>null],
            ['tenant'=>'Moses Odhiambo', 'unit'=>'B-205','month'=>'Mar 2026','amount'=>1380,'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-03-03'],
            ['tenant'=>'Sarah Rutto',    'unit'=>'A-101','month'=>'Feb 2026','amount'=>1200,'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-02-01'],
            ['tenant'=>'Brian Kimani',   'unit'=>'A-102','month'=>'Feb 2026','amount'=>950, 'method'=>'Cash',          'status'=>'paid',    'date'=>'2026-02-05'],
            ['tenant'=>'Amina Musa',     'unit'=>'B-201','month'=>'Feb 2026','amount'=>1400,'method'=>'Bank Transfer', 'status'=>'paid',    'date'=>'2026-02-01'],
            ['tenant'=>'James Omondi',   'unit'=>'C-301','month'=>'Feb 2026','amount'=>1100,'method'=>null,            'status'=>'overdue', 'date'=>null],
            ['tenant'=>'Fatima Ngugi',   'unit'=>'C-302','month'=>'Feb 2026','amount'=>1300,'method'=>'M-Pesa',        'status'=>'paid',    'date'=>'2026-02-02'],
        ];

        foreach ($data as $d) {
            $tenant = Tenant::where('name', $d['tenant'])->first();
            $unit   = Unit::where('unit_number', $d['unit'])->first();
            if (!$tenant || !$unit) continue;
            Payment::create([
                'tenant_id' => $tenant->id,
                'unit_id'   => $unit->id,
                'month'     => $d['month'],
                'amount'    => $d['amount'],
                'method'    => $d['method'],
                'status'    => $d['status'],
                'paid_date' => $d['date'],
            ]);
        }
    }
}
