<?php

namespace App\Models;

use App\Enums\ExpenseFundingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Expense extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'expense_number',
        'expense_date',
        'expense_account_id',
        'payment_account_id',
        'amount',
        'paid_by_partner_id',
        'funding_source',
        'description',
        'reference',
        'submission_token',
        'status',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'created_by',
        'reversed_by',
        'posted_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'funding_source' => ExpenseFundingSource::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $expense): void {
            if ($expense->getOriginal('status') === self::STATUS_POSTED) {
                $allowed = ['status', 'reversal_journal_entry_id', 'reversed_by', 'reversed_at', 'updated_at'];
                $isReversal = $expense->status === self::STATUS_REVERSED
                    && $expense->reversal_journal_entry_id
                    && $expense->reversed_at
                    && ! array_diff(array_keys($expense->getDirty()), $allowed);

                if ($isReversal) {
                    return;
                }

                throw ValidationException::withMessages(['expense' => 'لا يمكن تعديل مصروف مرحّل.']);
            }
            if ($expense->getOriginal('status') === self::STATUS_REVERSED) {
                throw ValidationException::withMessages(['expense' => 'المصروف المعكوس سجل محاسبي غير قابل للتعديل.']);
            }
        });
        static::deleting(function (self $expense): void {
            if (in_array($expense->status, [self::STATUS_POSTED, self::STATUS_REVERSED], true)) {
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

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function paidByPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'paid_by_partner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
