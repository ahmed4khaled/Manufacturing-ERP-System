<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Services\Accounting\DocumentPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseQuickExpenseAction
{
    public function __construct(private readonly DocumentPostingService $posting) {}

    public function handle(Expense $expense, ?string $date, ?int $reversedBy): Expense
    {
        return DB::transaction(function () use ($expense, $date, $reversedBy): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($expense->status === Expense::STATUS_REVERSED) {
                return $expense;
            }
            if ($expense->status !== Expense::STATUS_POSTED || ! $expense->journal_entry_id) {
                throw ValidationException::withMessages(['expense' => 'لا يمكن عكس مصروف غير مرحّل.']);
            }

            $reversal = $this->posting->reverse($expense, 'expense', $date);
            if (! $reversal) {
                throw ValidationException::withMessages(['expense' => 'تعذر إنشاء قيد عكس للمصروف.']);
            }

            $expense->update([
                'status' => Expense::STATUS_REVERSED,
                'reversal_journal_entry_id' => $reversal->id,
                'reversed_at' => now(),
                'reversed_by' => $reversedBy,
            ]);

            return $expense->fresh(['journalEntry', 'reversalJournalEntry']);
        });
    }
}
