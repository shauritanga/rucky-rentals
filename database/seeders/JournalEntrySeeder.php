<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JournalEntry;
use App\Models\JournalLine;

class JournalEntrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['id'=>'JE-001','date'=>'2026-03-01','desc'=>'Rent received — Sarah Rutto (A-101)','ref'=>'RENT-A101-Mar2026','status'=>'posted',
             'lines'=>[['acct'=>'1000','name'=>'Cash at Bank','dr'=>1200,'cr'=>0],['acct'=>'4000','name'=>'Rental Income','dr'=>0,'cr'=>1200]]],
            ['id'=>'JE-002','date'=>'2026-03-01','desc'=>'Rent received — Amina Musa (B-201)','ref'=>'RENT-B201-Mar2026','status'=>'posted',
             'lines'=>[['acct'=>'1000','name'=>'Cash at Bank','dr'=>1400,'cr'=>0],['acct'=>'4000','name'=>'Rental Income','dr'=>0,'cr'=>1400]]],
            ['id'=>'JE-003','date'=>'2026-03-17','desc'=>'Maintenance: Broken water pipe (D-401)','ref'=>'MAINT-TK-001','status'=>'posted',
             'lines'=>[['acct'=>'5000','name'=>'Maintenance Expense','dr'=>350,'cr'=>0],['acct'=>'2000','name'=>'Accounts Payable','dr'=>0,'cr'=>350]]],
            ['id'=>'JE-004','date'=>'2026-03-05','desc'=>'KPLC electricity bill — March 2026','ref'=>'UTIL-KPLC-MAR2026','status'=>'posted',
             'lines'=>[['acct'=>'5100','name'=>'Utilities Expense','dr'=>288900,'cr'=>0],['acct'=>'2000','name'=>'Accounts Payable','dr'=>0,'cr'=>288900]]],
            ['id'=>'JE-005','date'=>'2026-03-19','desc'=>'Monthly depreciation — March 2026','ref'=>'DEP-MAR2026','status'=>'posted',
             'lines'=>[['acct'=>'5300','name'=>'Depreciation Expense','dr'=>1800,'cr'=>0],['acct'=>'1600','name'=>'Accum. Depreciation','dr'=>0,'cr'=>1800]]],
            ['id'=>'JE-006','date'=>'2026-03-19','desc'=>'Insurance premium — Q1 2026','ref'=>'INS-Q1-2026','status'=>'draft',
             'lines'=>[['acct'=>'5200','name'=>'Insurance Expense','dr'=>840,'cr'=>0],['acct'=>'1200','name'=>'Prepaid Expenses','dr'=>0,'cr'=>840]]],
        ];

        foreach ($entries as $e) {
            $je = JournalEntry::create([
                'entry_number' => $e['id'],
                'entry_date'   => $e['date'],
                'description'  => $e['desc'],
                'reference'    => $e['ref'],
                'status'       => $e['status'],
            ]);
            foreach ($e['lines'] as $l) {
                JournalLine::create([
                    'journal_entry_id' => $je->id,
                    'account_code'     => $l['acct'],
                    'account_name'     => $l['name'],
                    'debit'            => $l['dr'],
                    'credit'           => $l['cr'],
                ]);
            }
        }
    }
}
