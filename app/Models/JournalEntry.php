<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class JournalEntry extends Model
{
    protected $fillable = ['entry_number', 'entry_date', 'description', 'reference_type', 'reference_id', 'entry_type', 'status', 'fiscal_period_id', 'reversal_of_id', 'reversed_by_id', 'posted_at', 'posted_by', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            if ($entry->status === 'posted') {
                throw ValidationException::withMessages(['journal_entry' => 'يجب إنشاء القيد كمسودة ثم ترحيله عبر محرك القيود.']);
            }
        });
        static::updating(function (self $entry): void {
            if ($entry->getOriginal('status') === 'posted') {
                $allowed = array_keys($entry->getDirty());
                if (array_diff($allowed, ['reversed_by_id', 'updated_at'])) {
                    throw ValidationException::withMessages(['journal_entry' => 'لا يمكن تعديل قيد مرحّل؛ استخدم قيد عكس.']);
                }
            }
            if ($entry->isDirty('status') && $entry->status === 'posted') {
                $totals = $entry->lines()->selectRaw('COUNT(*) line_count, COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first();
                if ((int) $totals->line_count < 2 || bccomp((string) $totals->debit, (string) $totals->credit, 2) !== 0 || bccomp((string) $totals->debit, '0', 2) <= 0) {
                    throw ValidationException::withMessages(['journal_entry' => 'لا يمكن ترحيل قيد غير متوازن أو بلا سطرين صالحين.']);
                }
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['journal_entry' => 'لا يمكن حذف القيود؛ استخدم العكس.']));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }
}
