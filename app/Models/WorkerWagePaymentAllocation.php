<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkerWagePaymentAllocation extends Model
{
    protected $fillable = [
        'worker_wage_payment_id',
        'production_worker_wage_id',
        'amount',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $allocation): void {
            if (array_diff(array_keys($allocation->getDirty()), ['reversed_at', 'updated_at'])) {
                throw ValidationException::withMessages(['allocation' => 'تخصيص دفعة الأجر غير قابل للتعديل.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages([
            'allocation' => 'لا يمكن حذف تخصيص دفعة أجر.',
        ]));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(WorkerWagePayment::class, 'worker_wage_payment_id');
    }

    public function wage(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkerWage::class, 'production_worker_wage_id');
    }
}
