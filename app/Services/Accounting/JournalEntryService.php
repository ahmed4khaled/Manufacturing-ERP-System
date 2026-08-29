<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Services\CodeGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function __construct(private readonly CodeGeneratorService $codes) {}

    public function account(string $mapping): Account
    {
        $account = AccountMapping::query()->with('account')->where('key', $mapping)->first()?->account;
        if (! $account) {
            throw ValidationException::withMessages(['account' => "لا يوجد ربط محاسبي للحساب: {$mapping}."]);
        }

        return $account;
    }

    /** @param list<array<string,mixed>> $lines */
    public function post(array $header, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines): JournalEntry {
            $existing = $this->existingReference($header);
            if ($existing) {
                return $existing->load('lines.account');
            }

            $this->validateLines($lines);
            $period = $this->openPeriodFor((string) $header['entry_date'], (bool) ($header['allow_closed_period'] ?? false));

            try {
                $entry = JournalEntry::query()->create([
                    'entry_number' => $header['entry_number'] ?? $this->codes->next('JRN'),
                    'entry_date' => $header['entry_date'],
                    'description' => $header['description'],
                    'reference_type' => $header['reference_type'] ?? null,
                    'reference_id' => $header['reference_id'] ?? null,
                    'entry_type' => $header['entry_type'] ?? 'manual',
                    'status' => 'draft',
                    'fiscal_period_id' => $period?->id,
                    'created_by' => $header['created_by'] ?? auth()->id(),
                    'reversal_of_id' => $header['reversal_of_id'] ?? null,
                ]);
            } catch (QueryException $exception) {
                $existing = $this->existingReference($header);
                if ($existing) {
                    return $existing->load('lines.account');
                }
                throw $exception;
            }

            foreach ($lines as $line) {
                $account = Account::query()->lockForUpdate()->findOrFail($line['account_id']);
                if (! $account->is_active || ! $account->allow_posting || $account->children()->exists()) {
                    throw ValidationException::withMessages(['account' => "الحساب {$account->code} غير صالح للترحيل المباشر."]);
                }
                $entry->lines()->create([
                    'account_id' => $account->id,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'partner_id' => $line['partner_id'] ?? null,
                ]);
            }

            $entry->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $header['posted_by'] ?? auth()->id()]);

            return $entry->load('lines.account');
        });
    }

    public function reverse(JournalEntry $original, ?string $date = null, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $date, $description): JournalEntry {
            $original = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($original->id);
            if ($original->status !== 'posted') {
                throw ValidationException::withMessages(['journal_entry' => 'لا يمكن عكس قيد غير مرحّل.']);
            }
            if ($original->reversed_by_id) {
                return JournalEntry::query()->with('lines.account')->findOrFail($original->reversed_by_id);
            }

            $reversal = $this->post([
                'entry_date' => $date ?? now()->toDateString(),
                'description' => $description ?? 'عكس القيد '.$original->entry_number,
                'reference_type' => JournalEntry::class,
                'reference_id' => $original->id,
                'entry_type' => 'reversal',
                'reversal_of_id' => $original->id,
            ], $original->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => $line->description,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'partner_id' => $line->partner_id,
            ])->all());
            $original->update(['reversed_by_id' => $reversal->id]);

            return $reversal;
        });
    }

    /** @param list<array<string,mixed>> $lines */
    private function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'يجب أن يحتوي القيد على سطرين على الأقل.']);
        }
        $debit = '0.00';
        $credit = '0.00';
        foreach ($lines as $index => $line) {
            $dr = bcadd((string) ($line['debit'] ?? 0), '0', 2);
            $cr = bcadd((string) ($line['credit'] ?? 0), '0', 2);
            if (bccomp($dr, '0', 2) < 0 || bccomp($cr, '0', 2) < 0 || (bccomp($dr, '0', 2) > 0) === (bccomp($cr, '0', 2) > 0)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'يجب أن يحتوي السطر على مبلغ موجب في المدين أو الدائن فقط.']);
            }
            $debit = bcadd($debit, $dr, 2);
            $credit = bcadd($credit, $cr, 2);
        }
        if (bccomp($debit, $credit, 2) !== 0) {
            throw ValidationException::withMessages(['lines' => "القيد غير متوازن: المدين {$debit} والدائن {$credit}."]);
        }
    }

    private function openPeriodFor(string $date, bool $allowClosed = false): ?FiscalPeriod
    {
        $period = FiscalPeriod::query()->with('fiscalYear')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->lockForUpdate()->first();
        if ($period && ((! $allowClosed && $period->status !== 'open') || $period->fiscalYear->status !== 'open')) {
            throw ValidationException::withMessages(['entry_date' => 'الفترة المالية لهذا التاريخ مغلقة.']);
        }
        $yearExists = FiscalYear::query()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();
        if ($yearExists && ! $period) {
            throw ValidationException::withMessages(['entry_date' => 'لا توجد فترة مالية معرفة لهذا التاريخ داخل السنة المالية.']);
        }

        return $period;
    }

    private function existingReference(array $header): ?JournalEntry
    {
        if (empty($header['reference_type']) || empty($header['reference_id'])) {
            return null;
        }

        return JournalEntry::query()
            ->where('reference_type', $header['reference_type'])
            ->where('reference_id', $header['reference_id'])
            ->where('entry_type', $header['entry_type'] ?? 'manual')
            ->first();
    }
}
