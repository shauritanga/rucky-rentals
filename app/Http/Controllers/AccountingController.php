<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function index()
    {
        $accounts = Account::orderBy('code')->get();
        $entries  = JournalEntry::with('lines')->orderByDesc('entry_date')->get();
        return Inertia::render('Accounting/Index', compact('accounts', 'entries'));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|unique:accounts',
            'name'        => 'required|string',
            'type'        => 'required|in:asset,liability,equity,revenue,expense,contra',
            'category'    => 'nullable|string',
            'balance'     => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);
        Account::create($data);
        return back()->with('success', 'Account created.');
    }

    public function storeJournalEntry(Request $request)
    {
        $data = $request->validate([
            'entry_date'  => 'required|date',
            'description' => 'required|string',
            'reference'   => 'nullable|string',
            'status'      => 'required|in:draft,posted',
            'lines'       => 'required|array|min:2',
            'lines.*.account_code' => 'required|string',
            'lines.*.account_name' => 'required|string',
            'lines.*.debit'        => 'required|numeric|min:0',
            'lines.*.credit'       => 'required|numeric|min:0',
        ]);

        $count = JournalEntry::count() + 1;
        $je = JournalEntry::create([
            'entry_number' => 'JE-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'entry_date'   => $data['entry_date'],
            'description'  => $data['description'],
            'reference'    => $data['reference'] ?? null,
            'status'       => $data['status'],
        ]);

        foreach ($data['lines'] as $line) {
            JournalLine::create([
                'journal_entry_id' => $je->id,
                'account_code'     => $line['account_code'],
                'account_name'     => $line['account_name'],
                'debit'            => $line['debit'],
                'credit'           => $line['credit'],
            ]);
        }

        return back()->with('success', 'Journal entry saved.');
    }

    public function updateJournalEntry(Request $request, JournalEntry $journalEntry)
    {
        $journalEntry->update($request->only(['status']));
        return back()->with('success', 'Entry updated.');
    }
}
