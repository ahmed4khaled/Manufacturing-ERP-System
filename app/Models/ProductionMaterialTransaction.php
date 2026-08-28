<?php

namespace App\Models;

use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterialTransaction extends Model
{
    protected $fillable = ['production_order_id', 'work_assignment_id', 'work_assignment_material_id', 'worker_id', 'material_id', 'type', 'quantity', 'actual_cost', 'status', 'occurred_at', 'notes', 'recorded_by', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['type' => ProductionMaterialTransactionType::class, 'status' => ProductionMaterialTransactionStatus::class, 'quantity' => 'decimal:3', 'actual_cost' => 'decimal:2', 'occurred_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function workAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class);
    }

    public function assignmentMaterial(): BelongsTo
    {
        return $this->belongsTo(WorkAssignmentMaterial::class, 'work_assignment_material_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
