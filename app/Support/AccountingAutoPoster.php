<?php

namespace App\Support;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class AccountingAutoPoster
{
    /**
     * Default chart metadata used when an auto-post needs a missing account.
     * This keeps automation resilient per property without manual setup.
     */
    private const ACCOUNT_TEMPLATES = [
        '1000' => ['name' => 'Cash at Bank',          'type' => 'asset',     'category' => 'Current Assets'],
        '1100' => ['name' => 'Rent Receivable',        'type' => 'asset',     'category' => 'Current Assets'],
        '1110' => ['name' => 'Security Deposits',      'type' => 'asset',     'category' => 'Current Assets'],
        '1120' => ['name' => 'WHT Tax Credit',         'type' => 'asset',     'category' => 'Current Assets'],
        '2000' => ['name' => 'Accounts Payable',       'type' => 'liability', 'category' => 'Current Liabilities'],
        '2100' => ['name' => 'Deposits Payable',       'type' => 'liability', 'category' => 'Current Liabilities'],
        '2500' => ['name' => 'Management Fee Payable', 'type' => 'liability', 'category' => 'Current Liabilities'],
        '4000' => ['name' => 'Rental Income',          'type' => 'revenue',   'category' => 'Operating Revenue'],
        '4100' => ['name' => 'Service Charge Income',  'type' => 'revenue',   'category' => 'Operating Revenue'],
        '5000' => ['name' => 'Maintenance Expense',    'type' => 'expense',   'category' => 'Operating Expenses'],
        '5100' => ['name' => 'Utilities Expense',      'type' => 'expense',   'category' => 'Operating Expenses'],
        '5500' => ['name' => 'Management Fee',         'type' => 'expense',   'category' => 'Operating Expenses'],
    ];

    public function post(
        ?int $propertyId,
        string $entryDate,
        string $description,
        string $reference,
        array $lines,
        string $sourceType = 'system',
        ?int $sourceId = null
    ): JournalEntry {
        return DB::transaction(function () use ($propertyId, $entryDate, $description, $reference, $lines, $sourceType, $sourceId) {
            $this->assertBalancedLines($lines);

            $existing = JournalEntry::query()
                ->where('reference', $reference)
                ->where('property_id', $propertyId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'property_id' => $propertyId,
                'entry_number' => $this->nextEntryNumber(),
                'entry_date' => $entryDate,
                'description' => $description,
                'reference' => $reference,
                'status' => 'posted',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            foreach ($lines as $line) {
                $account = $this->resolveAccount(
                    $propertyId,
                    (string) ($line['account_code'] ?? ''),
                    (string) ($line['account_name'] ?? ''),
                    (string) ($line['type'] ?? 'asset'),
                    (string) ($line['category'] ?? 'General')
                );

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                ]);
            }

            $this->applyBalances($entry);

            return $entry;
        });
    }

    private function assertBalancedLines(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $lineDebit = (float) ($line['debit'] ?? 0);
            $lineCredit = (float) ($line['credit'] ?? 0);

            if (($lineDebit > 0 && $lineCredit > 0) || ($lineDebit <= 0 && $lineCredit <= 0)) {
                throw new InvalidArgumentException('Each journal line must have exactly one side: debit or credit.');
            }

            $debit += $lineDebit;
            $credit += $lineCredit;
        }

        if (abs($debit - $credit) > 0.01) {
            throw new InvalidArgumentException('Journal entry is not balanced. Total debit must equal total credit.');
        }
    }

    /**
     * Apply (or reverse) the balance impact of a posted journal entry.
     *
     * BALANCE SIGN CONVENTION — read before changing:
     *   balance = Σ(debits) − Σ(credits)  for ALL account types.
     *
     *   This signed-delta approach means:
     *     • Asset / Expense   (debit-normal): positive balance = net debit = value ✓
     *     • Liability / Equity / Revenue (credit-normal): negative balance = net credit = value ✓
     *
     *   The frontend's displayBalance() negates liability/equity/revenue accounts before
     *   showing them to users, so the numbers appear positive and intuitive in every report.
     *
     *   Do NOT change this to a "store absolute value" approach without also updating
     *   every report calculation and displayBalance() in the frontend — the convention is
     *   internally consistent and changing only half of it will break financial statements.
     */
    public function applyBalances(JournalEntry $entry, bool $reverse = false): void
    {
        $multiplier = $reverse ? -1 : 1;

        foreach ($entry->lines as $line) {
            $delta = ((float) $line->debit - (float) $line->credit) * $multiplier;

            Account::query()
                ->where('code', $line->account_code)
                ->where('property_id', $entry->property_id)
                ->increment('balance', $delta);

            Account::query()
                ->where('code', $line->account_code)
                ->where('property_id', $entry->property_id)
                ->increment('ytd_activity', $delta);
        }
    }

    public function voidByReference(?int $propertyId, string $reference): void
    {
        DB::transaction(function () use ($propertyId, $reference) {
            $entry = JournalEntry::query()
                ->with('lines')
                ->where('reference', $reference)
                ->where('property_id', $propertyId)
                ->first();

            if (!$entry || $entry->status !== 'posted') {
                return;
            }

            $this->applyBalances($entry, true);
            $entry->update(['status' => 'void']);
        });
    }

    private function resolveAccount(?int $propertyId, string $code, string $name, string $type, string $category): Account
    {
        $template = self::ACCOUNT_TEMPLATES[$code] ?? null;

        return Account::query()->firstOrCreate(
            ['property_id' => $propertyId, 'code' => $code],
            [
                'name' => $name !== '' ? $name : ($template['name'] ?? 'Auto Account ' . $code),
                'type' => $type !== '' ? $type : ($template['type'] ?? 'asset'),
                'category' => $category !== '' ? $category : ($template['category'] ?? 'General'),
                'balance' => 0,
                'ytd_activity' => 0,
                'description' => 'Auto-created by accounting automation.',
            ]
        );
    }

    private function nextEntryNumber(): string
    {
        $max = JournalEntry::query()
            ->lockForUpdate()
            ->select('entry_number')
            ->pluck('entry_number')
            ->map(function ($id) {
                return preg_match('/JE-(\d+)/', (string) $id, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return 'JE-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
