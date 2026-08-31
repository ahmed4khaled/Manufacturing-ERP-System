<?php

namespace App\Models;

use App\Enums\WorkerWagePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkerWagePayment extends Model
{
    protected $fillable = [
        'payment_number',
        'worker_id',
        'payment_date',
        'payment_account_id',
        'amount',
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
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'status' => WorkerWagePaymentStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if ($payment->getOriginal('status') === WorkerWagePaymentStatus::POSTED->value) {
                $allowed = ['status', 'reversal_journal_entry_id', 'reversed_by', 'reversed_at', 'updated_at'];
                $isReversal = $payment->status === WorkerWagePaymentStatus::REVERSED
                    && $payment->reversal_journal_entry_id
                    && $payment->reversed_at
                    && ! array_diff(array_keys($payment->getDirty()), $allowed);

                if ($isReversal) {
                    return;
                }

                throw ValidationException::withMessages(['payment' => 'لا يمكن تعديل دفعة أجر مرحّلة.']);
            }
            if ($payment->getOriginal('status') === WorkerWagePaymentStatus::REVERSED->value) {
                throw ValidationException::withMessages(['payment' => 'دفعة الأجر المعكوسة غير قابلة للتعديل.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages([
            'payment' => 'لا يمكن حذف دفعة أجر؛ استخدم العكس.',
        ]));
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(WorkerWagePaymentAllocation::class);
    }
}
