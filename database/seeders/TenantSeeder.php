<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            ['name'=>'Sarah Rutto',    'email'=>'sarah.rutto@gmail.com',    'phone'=>'+254 712 345 678','national_id'=>'KE-34521890','initials'=>'SR','color'=>'rgba(59,130,246,.18)','text_color'=>'var(--accent)', 'nok_name'=>'Grace Rutto',     'nok_phone'=>'+254 700 111 222','nok_relation'=>'Sister'],
            ['name'=>'Brian Kimani',   'email'=>'b.kimani@outlook.com',     'phone'=>'+254 722 100 200','national_id'=>'KE-12094567','initials'=>'BK','color'=>'rgba(34,197,94,.18)', 'text_color'=>'var(--green)',  'nok_name'=>'Alice Kimani',    'nok_phone'=>'+254 722 333 444','nok_relation'=>'Mother', 'notes'=>'Overdue since Mar 5. Two missed payments total.'],
            ['name'=>'Amina Musa',     'email'=>'amina.musa@gmail.com',     'phone'=>'+254 733 887 654','national_id'=>'KE-56781234','initials'=>'AM','color'=>'rgba(245,158,11,.18)','text_color'=>'var(--amber)',  'nok_name'=>'Omar Musa',       'nok_phone'=>'+254 733 555 666','nok_relation'=>'Husband'],
            ['name'=>'James Omondi',   'email'=>'james.omondi@yahoo.com',   'phone'=>'+254 700 234 567','national_id'=>'KE-78902345','initials'=>'JO','color'=>'rgba(239,68,68,.18)', 'text_color'=>'var(--red)',    'nok_name'=>'Mary Omondi',     'nok_phone'=>'+254 700 777 888','nok_relation'=>'Wife',   'notes'=>'2 months overdue — $2,200 balance. Notice issued Feb 28.'],
            ['name'=>'Fatima Ngugi',   'email'=>'f.ngugi@proton.me',        'phone'=>'+254 711 998 001','national_id'=>'KE-23456789','initials'=>'FN','color'=>'rgba(59,130,246,.18)','text_color'=>'var(--accent)', 'nok_name'=>'Hassan Ngugi',    'nok_phone'=>'+254 711 999 000','nok_relation'=>'Brother'],
            ['name'=>'Charles Kiprop', 'email'=>'c.kiprop@gmail.com',       'phone'=>'+254 729 456 123','national_id'=>'KE-90123456','initials'=>'CK','color'=>'rgba(34,197,94,.18)', 'text_color'=>'var(--green)',  'nok_name'=>'Esther Kiprop',   'nok_phone'=>'+254 729 112 223','nok_relation'=>'Spouse'],
            ['name'=>'Lydia Wambui',   'email'=>'lydia.w@gmail.com',        'phone'=>'+254 745 321 654','national_id'=>'KE-34567012','initials'=>'LW','color'=>'rgba(245,158,11,.18)','text_color'=>'var(--amber)',  'nok_name'=>'John Wambui',     'nok_phone'=>'+254 745 334 445','nok_relation'=>'Husband'],
            ['name'=>'Peter Otieno',   'email'=>'p.otieno@gmail.com',       'phone'=>'+254 788 654 321','national_id'=>'KE-45678901','initials'=>'PO','color'=>'rgba(59,130,246,.18)','text_color'=>'var(--accent)', 'nok_name'=>'Eunice Otieno',   'nok_phone'=>'+254 788 556 667','nok_relation'=>'Mother'],
            ['name'=>'Nancy Mwende',   'email'=>'n.mwende@gmail.com',       'phone'=>'+254 700 112 233','national_id'=>'KE-56789012','initials'=>'NM','color'=>'rgba(239,68,68,.18)', 'text_color'=>'var(--red)',    'nok_name'=>'Stephen Mwende',  'nok_phone'=>'+254 700 778 889','nok_relation'=>'Father'],
            ['name'=>'David Kamau',    'email'=>'david.kamau@gmail.com',    'phone'=>'+254 733 445 566','national_id'=>'KE-67890123','initials'=>'DK','color'=>'rgba(34,197,94,.18)', 'text_color'=>'var(--green)',  'nok_name'=>'Ruth Kamau',      'nok_phone'=>'+254 733 990 001','nok_relation'=>'Wife'],
            ['name'=>'Rose Njeri',     'email'=>'rose.njeri@gmail.com',     'phone'=>'+254 711 778 899','national_id'=>'KE-78901234','initials'=>'RN','color'=>'rgba(245,158,11,.18)','text_color'=>'var(--amber)',  'nok_name'=>'Patrick Njeri',   'nok_phone'=>'+254 711 223 334','nok_relation'=>'Father'],
            ['name'=>'Moses Odhiambo', 'email'=>'m.odhiambo@gmail.com',    'phone'=>'+254 722 334 455','national_id'=>'KE-89012345','initials'=>'MO','color'=>'rgba(59,130,246,.18)','text_color'=>'var(--accent)', 'nok_name'=>'Clara Odhiambo',  'nok_phone'=>'+254 722 445 556','nok_relation'=>'Sister'],
        ];

        foreach ($tenants as $t) {
            Tenant::create($t);
        }
    }
}
