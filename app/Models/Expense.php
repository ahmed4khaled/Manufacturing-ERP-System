<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Expense extends Model
{
    protected $fillable = ['expense_number', 'expense_date', 'expense_account_id', 'payment_account_id', 'amount', 'description', 'reference', 'status', 'journal_entry_id', 'created_by', 'posted_at'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $expense): void {
            if ($expense->getOriginal('status') === 'posted') {
                throw ValidationException::withMessages(['expense' => 'لا يمكن تعديل مصروف مرحّل.']);
            }
        });
        static::deleting(function (self $expense): void {
            if ($expense->status === 'posted') {
                throw ValidationException::withMessages(['expense' => 'لا يمكن حذف مصروف مرحّل؛ استخدم قيد عكس.']);
            }
        });
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
