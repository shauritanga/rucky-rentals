<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Property;
use App\Support\AccountingAutoPoster;
use App\Traits\LogsAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    use LogsAudit;
    public function index(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request, false);

        $accounts = Account::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->orderBy('code')
            ->get();

        $from = $request->input('from');
        $to   = $request->input('to');

        $entries = JournalEntry::query()
            ->with('lines')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->when($from, fn($q) => $q->where('entry_date', '>=', $from))
            ->when($to,   fn($q) => $q->where('entry_date', '<=', $to))
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

        $resolvedPropertyId = $propertyId ?? ($request->filled('property_id') ? (int) $request->input('property_id') : null);
        $account = Account::create([
            ...$data,
            'property_id' => $resolvedPropertyId,
        ]);

        $propertyName = Property::where('id', $resolvedPropertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Account created',
            resource: sprintf('%s (%s)', $account->name, $account->code),
            propertyName: $propertyName,
            category: 'accounting',
            propertyId: $resolvedPropertyId ? (int) $resolvedPropertyId : null,
        );

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

        $totalDebit = collect($data['lines'])->sum(fn($line) => (float) $line['debit']);
        $totalCredit = collect($data['lines'])->sum(fn($line) => (float) $line['credit']);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withErrors([
                'lines' => 'Journal entry is not balanced. Total debit must equal total credit.',
            ]);
        }

        foreach ($data['lines'] as $line) {
            $debit = (float) $line['debit'];
            $credit = (float) $line['credit'];
            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                return back()->withErrors([
                    'lines' => 'Each journal line must have exactly one side: debit or credit.',
                ]);
            }
        }

        DB::transaction(function () use ($data, $propertyId, $poster) {
            $count = JournalEntry::lockForUpdate()
                ->select('entry_number')
                ->pluck('entry_number')
                ->map(function ($entryNumber) {
                    return preg_match('/JE-(\d+)/', (string) $entryNumber, $matches) ? (int) $matches[1] : 0;
                })
                ->max() + 1;

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

        $entryNumber  = JournalEntry::where('property_id', $propertyId)->orderByDesc('id')->value('entry_number');
        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Journal entry created',
            resource: $entryNumber ?? 'JE',
            propertyName: $propertyName,
            category: 'accounting',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

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

        $propertyName = Property::where('id', $journalEntry->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Journal entry updated',
            resource: $journalEntry->entry_number,
            propertyName: $propertyName,
            category: 'accounting',
            metadata: ['status' => $data['status']],
            propertyId: $journalEntry->property_id ? (int) $journalEntry->property_id : null,
        );

        return back()->with('success', 'Entry updated.');
    }

    private function resolvePropertyId(Request $request, bool $mustExist): ?int
    {
        // Manager or superuser in property-view mode — scope to effective property
        if ($this->shouldScopeToProperty($request)) {
            return $this->effectivePropertyId($request);
        }

        $requestedPropertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;
        if ($mustExist && $requestedPropertyId !== null) {
            abort_if(!Property::where('id', $requestedPropertyId)->exists(), 422, 'Property not found.');
        }

        return $requestedPropertyId;
    }
}
