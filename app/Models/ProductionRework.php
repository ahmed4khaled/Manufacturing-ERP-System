<?php

namespace App\Models;

use App\Enums\ProductionReworkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRework extends Model
{
    protected $fillable = ['production_defect_id', 'production_order_id', 'source_work_assignment_id', 'production_order_operation_id', 'worker_id', 'assigned_quantity', 'good_quantity', 'defective_quantity', 'status', 'notes', 'created_by', 'completed_by', 'started_at', 'completed_at'];

    protected $casts = ['assigned_quantity' => 'decimal:3', 'good_quantity' => 'decimal:3', 'defective_quantity' => 'decimal:3', 'status' => ProductionReworkStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function defect(): BelongsTo
    {
        return $this->belongsTo(ProductionDefect::class, 'production_defect_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function sourceAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class, 'source_work_assignment_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }
}
