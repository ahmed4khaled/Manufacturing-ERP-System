<?php

namespace App\Models;

use App\Enums\WorkAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkAssignment extends Model
{
    protected $fillable = ['production_order_id', 'production_order_operation_id', 'worker_id', 'assigned_quantity', 'produced_quantity', 'good_quantity', 'defective_quantity', 'status', 'started_at', 'completed_at', 'notes', 'assigned_by', 'completed_by'];

    protected $casts = ['assigned_quantity' => 'decimal:3', 'produced_quantity' => 'decimal:3', 'good_quantity' => 'decimal:3', 'defective_quantity' => 'decimal:3', 'status' => WorkAssignmentStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkAssignmentMaterial::class);
    }

    public function materialTransactions(): HasMany
    {
        return $this->hasMany(ProductionMaterialTransaction::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(ProductionDefect::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function productionWage(): HasOne
    {
        return $this->hasOne(ProductionWorkerWage::class);
    }
}
