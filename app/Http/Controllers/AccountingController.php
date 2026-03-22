<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Property;
use App\Support\AccountingAutoPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request, false);

        $accounts = Account::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->orderBy('code')
            ->get();

        $entries = JournalEntry::query()
            ->with('lines')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->orderByDesc('entry_date')
            ->get();

        return Inertia::render('Accounting/Index', compact('accounts', 'entries'));
    }

    public function storeAccount(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request, true);

        $data = $request->validate([
            'property_id' => ['nullable', Rule::exists('properties', 'id')],
            'code'        => [
                'required',
                'string',
                Rule::unique('accounts', 'code')->where(function ($q) use ($propertyId, $request) {
                    $scopePropertyId = $propertyId;
                    if ($scopePropertyId === null && $request->filled('property_id')) {
                        $scopePropertyId = (int) $request->input('property_id');
                    }

                    if ($scopePropertyId === null) {
                        $q->whereNull('property_id');
                    } else {
                        $q->where('property_id', $scopePropertyId);
                    }
                }),
            ],
            'name'        => 'required|string',
            'type'        => 'required|in:asset,liability,equity,revenue,expense,contra',
            'category'    => 'nullable|string',
            'balance'     => 'nullable|numeric',
            'ytd_activity' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        Account::create([
            ...$data,
            'property_id' => $propertyId ?? ($request->filled('property_id') ? (int) $request->input('property_id') : null),
        ]);

        return back()->with('success', 'Account created.');
    }

    public function storeJournalEntry(Request $request, AccountingAutoPoster $poster)
    {
        $propertyId = $this->resolvePropertyId($request, true);

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

        DB::transaction(function () use ($data, $propertyId, $poster) {
            $count = JournalEntry::count() + 1;
            $je = JournalEntry::create([
                'property_id'  => $propertyId,
                'entry_number' => 'JE-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT),
                'entry_date'   => $data['entry_date'],
                'description'  => $data['description'],
                'reference'    => $data['reference'] ?? null,
                'status'       => $data['status'],
                'source_type'  => 'manual',
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

            if ($je->status === 'posted') {
                $poster->applyBalances($je->load('lines'));
            }
        });

        return back()->with('success', 'Journal entry saved.');
    }

    public function updateJournalEntry(Request $request, JournalEntry $journalEntry, AccountingAutoPoster $poster)
    {
        $propertyId = $this->resolvePropertyId($request, true);
        if ($propertyId !== null) {
            abort_if((int) $journalEntry->property_id !== $propertyId, 403);
        }

        $data = $request->validate([
            'status' => 'required|in:draft,posted,void',
        ]);

        $previous = $journalEntry->status;
        $next = $data['status'];

        DB::transaction(function () use ($journalEntry, $poster, $previous, $next) {
            $journalEntry->update(['status' => $next]);

            $entryWithLines = $journalEntry->fresh()->load('lines');

            if ($previous !== 'posted' && $next === 'posted') {
                $poster->applyBalances($entryWithLines);
            }

            if ($previous === 'posted' && $next !== 'posted') {
                $poster->applyBalances($entryWithLines, true);
            }
        });

        return back()->with('success', 'Entry updated.');
    }

    private function resolvePropertyId(Request $request, bool $mustExist): ?int
    {
        $user = $request->user();

        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'Manager is not assigned to any property.');
            abort_if(!Property::where('id', $user->property_id)->exists(), 422, 'Assigned property not found.');

            return (int) $user->property_id;
        }

        $requestedPropertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;
        if ($mustExist && $requestedPropertyId !== null) {
            abort_if(!Property::where('id', $requestedPropertyId)->exists(), 422, 'Property not found.');
        }

        return $requestedPropertyId;
    }
}
