<?php

namespace App\Models;

use App\Enums\ProductionDefectReason;
use App\Enums\ProductionDefectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionDefect extends Model
{
    protected $fillable = ['production_order_id', 'work_assignment_id', 'worker_id', 'production_order_operation_id', 'product_variant_id', 'quantity', 'reason', 'status', 'notes', 'recorded_by', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['quantity' => 'decimal:3', 'reason' => ProductionDefectReason::class, 'status' => ProductionDefectStatus::class, 'reviewed_at' => 'datetime'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function workAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionRework::class);
    }
}
