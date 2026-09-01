<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CutoverBatch extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'batch_number', 'reference', 'cutover_date', 'description', 'status',
        'journal_entry_id', 'reversal_journal_entry_id', 'created_by', 'posted_by',
        'reversed_by', 'posted_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'cutover_date' => 'date',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $batch): void {
            if ($batch->getOriginal('status') !== self::STATUS_DRAFT) {
                $allowed = ['status', 'journal_entry_id', 'reversal_journal_entry_id', 'posted_by', 'reversed_by', 'posted_at', 'reversed_at', 'updated_at'];
                if (array_diff(array_keys($batch->getDirty()), $allowed)) {
                    throw ValidationException::withMessages(['cutover' => 'دفعة الـCutover المرحلة غير قابلة للتعديل؛ استخدم العكس.']);
                }
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages([
            'cutover' => 'لا يمكن حذف دفعات الـCutover؛ احتفظ بسجل التدقيق واستخدم العكس عند الحاجة.',
        ]));
    }

    public function balances(): HasMany
    {
        return $this->hasMany(CutoverOpeningBalance::class);
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

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
