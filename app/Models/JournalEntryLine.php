<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class JournalEntryLine extends Model
{
    protected $fillable = ['journal_entry_id', 'cutover_opening_balance_id', 'account_id', 'debit', 'credit', 'description', 'customer_id', 'supplier_id', 'partner_id', 'worker_id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $validate = function (self $line): void {
            $debit = bcadd((string) ($line->debit ?? 0), '0', 2);
            $credit = bcadd((string) ($line->credit ?? 0), '0', 2);
            if (bccomp($debit, '0', 2) < 0 || bccomp($credit, '0', 2) < 0 || (bccomp($debit, '0', 2) > 0) === (bccomp($credit, '0', 2) > 0)) {
                throw ValidationException::withMessages(['journal_line' => 'سطر القيد يجب أن يكون مدينًا أو دائنًا بمبلغ موجب، وليس كليهما.']);
            }
        };
        $guard = function (self $line): void {
            $entry = $line->journalEntry()->first();
            if ($entry?->status === 'posted') {
                throw ValidationException::withMessages(['journal_entry' => 'سطور القيد المرحّل غير قابلة للتعديل.']);
            }
        };
        static::creating($validate);
        static::updating($validate);
        static::updating($guard);
        static::deleting($guard);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function cutoverOpeningBalance(): BelongsTo
    {
        return $this->belongsTo(CutoverOpeningBalance::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }
}
