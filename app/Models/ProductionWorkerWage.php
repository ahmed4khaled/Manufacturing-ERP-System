<?php

namespace App\Models;

use App\Enums\ProductionWorkerWageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProductionWorkerWage extends Model
{
    protected $fillable = [
        'worker_id',
        'production_order_id',
        'work_assignment_id',
        'production_order_operation_id',
        'quantity',
        'unit_rate',
        'gross_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_rate' => 'decimal:4',
            'gross_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'status' => ProductionWorkerWageStatus::class,
            'earned_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $wage): void {
            $allowed = ['paid_amount', 'remaining_amount', 'status', 'updated_at'];

            if (array_diff(array_keys($wage->getDirty()), $allowed)) {
                throw ValidationException::withMessages([
                    'wage' => 'لقطة استحقاق أجر الإنتاج غير قابلة للتعديل.',
                ]);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages([
            'wage' => 'لا يمكن حذف استحقاق أجر إنتاج.',
        ]));
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class)->withTrashed();
    }

    public function workAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(WorkerWagePaymentAllocation::class);
    }
}
