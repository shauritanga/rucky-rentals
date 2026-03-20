<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            // Floor 1
            ['unit_number'=>'A-101','floor'=>1,'type'=>'1 Bed',    'size_sqft'=>620, 'rent'=>1200,'status'=>'occupied',    'deposit'=>2400],
            ['unit_number'=>'A-102','floor'=>1,'type'=>'1 Bed',    'size_sqft'=>600, 'rent'=>950, 'status'=>'overdue',     'deposit'=>1900],
            ['unit_number'=>'A-103','floor'=>1,'type'=>'Studio',   'size_sqft'=>420, 'rent'=>750, 'status'=>'occupied',    'deposit'=>1500],
            ['unit_number'=>'A-104','floor'=>1,'type'=>'2 Bed',    'size_sqft'=>880, 'rent'=>1450,'status'=>'occupied',    'deposit'=>2900],
            ['unit_number'=>'A-105','floor'=>1,'type'=>'1 Bed',    'size_sqft'=>610, 'rent'=>920, 'status'=>'occupied',    'deposit'=>1840],
            ['unit_number'=>'A-106','floor'=>1,'type'=>'Studio',   'size_sqft'=>400, 'rent'=>720, 'status'=>'occupied',    'deposit'=>1440],
            // Floor 2
            ['unit_number'=>'B-201','floor'=>2,'type'=>'2 Bed',    'size_sqft'=>920, 'rent'=>1400,'status'=>'occupied',    'deposit'=>2800],
            ['unit_number'=>'B-202','floor'=>2,'type'=>'1 Bed',    'size_sqft'=>650, 'rent'=>1250,'status'=>'vacant',      'deposit'=>0],
            ['unit_number'=>'B-203','floor'=>2,'type'=>'Studio',   'size_sqft'=>430, 'rent'=>780, 'status'=>'occupied',    'deposit'=>1560],
            ['unit_number'=>'B-204','floor'=>2,'type'=>'1 Bed',    'size_sqft'=>640, 'rent'=>1050,'status'=>'occupied',    'deposit'=>2100],
            ['unit_number'=>'B-205','floor'=>2,'type'=>'2 Bed',    'size_sqft'=>890, 'rent'=>1380,'status'=>'occupied',    'deposit'=>2760],
            // Floor 3
            ['unit_number'=>'C-301','floor'=>3,'type'=>'2 Bed',    'size_sqft'=>900, 'rent'=>1100,'status'=>'overdue',     'deposit'=>2200],
            ['unit_number'=>'C-302','floor'=>3,'type'=>'2 Bed',    'size_sqft'=>940, 'rent'=>1300,'status'=>'occupied',    'deposit'=>2600],
            ['unit_number'=>'C-303','floor'=>3,'type'=>'Studio',   'size_sqft'=>415, 'rent'=>760, 'status'=>'occupied',    'deposit'=>1520],
            ['unit_number'=>'C-304','floor'=>3,'type'=>'1 Bed',    'size_sqft'=>630, 'rent'=>1000,'status'=>'occupied',    'deposit'=>2000],
            ['unit_number'=>'C-305','floor'=>3,'type'=>'3 Bed',    'size_sqft'=>1100,'rent'=>1800,'status'=>'occupied',    'deposit'=>3600],
            // Floor 4
            ['unit_number'=>'D-401','floor'=>4,'type'=>'1 Bed',    'size_sqft'=>660, 'rent'=>1500,'status'=>'maintenance', 'deposit'=>0],
            ['unit_number'=>'D-402','floor'=>4,'type'=>'2 Bed',    'size_sqft'=>950, 'rent'=>1600,'status'=>'occupied',    'deposit'=>3200],
            ['unit_number'=>'D-403','floor'=>4,'type'=>'Studio',   'size_sqft'=>440, 'rent'=>820, 'status'=>'occupied',    'deposit'=>1640],
            ['unit_number'=>'D-404','floor'=>4,'type'=>'1 Bed',    'size_sqft'=>645, 'rent'=>1100,'status'=>'vacant',      'deposit'=>0],
            // Floor 5
            ['unit_number'=>'E-501','floor'=>5,'type'=>'2 Bed',    'size_sqft'=>960, 'rent'=>1650,'status'=>'occupied',    'deposit'=>3300],
            ['unit_number'=>'E-502','floor'=>5,'type'=>'3 Bed',    'size_sqft'=>1150,'rent'=>2000,'status'=>'occupied',    'deposit'=>4000],
            ['unit_number'=>'E-503','floor'=>5,'type'=>'1 Bed',    'size_sqft'=>670, 'rent'=>1150,'status'=>'occupied',    'deposit'=>2300],
            ['unit_number'=>'E-504','floor'=>5,'type'=>'Studio',   'size_sqft'=>425, 'rent'=>800, 'status'=>'occupied',    'deposit'=>1600],
            ['unit_number'=>'E-505','floor'=>5,'type'=>'2 Bed',    'size_sqft'=>980, 'rent'=>1700,'status'=>'occupied',    'deposit'=>3400],
            // Floor 6
            ['unit_number'=>'F-601','floor'=>6,'type'=>'Penthouse','size_sqft'=>2100,'rent'=>4500,'status'=>'occupied',    'deposit'=>9000],
            ['unit_number'=>'F-602','floor'=>6,'type'=>'3 Bed',    'size_sqft'=>1250,'rent'=>2400,'status'=>'occupied',    'deposit'=>4800],
            ['unit_number'=>'F-603','floor'=>6,'type'=>'3 Bed',    'size_sqft'=>1200,'rent'=>2200,'status'=>'occupied',    'deposit'=>4400],
            ['unit_number'=>'F-604','floor'=>6,'type'=>'2 Bed',    'size_sqft'=>1020,'rent'=>1900,'status'=>'occupied',    'deposit'=>3800],
            ['unit_number'=>'F-605','floor'=>6,'type'=>'Penthouse','size_sqft'=>2050,'rent'=>4200,'status'=>'occupied',    'deposit'=>8400],
        ];

        foreach ($units as $u) {
            Unit::create($u);
        }
    }
}
