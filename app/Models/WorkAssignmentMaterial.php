<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkAssignmentMaterial extends Model
{
    protected $fillable = ['work_assignment_id', 'production_order_material_id', 'production_order_material_operation_id', 'standard_quantity', 'allocated_quantity', 'is_overridden', 'override_reason', 'allocated_by'];

    protected $casts = ['standard_quantity' => 'decimal:3', 'allocated_quantity' => 'decimal:3', 'is_overridden' => 'boolean'];

    public function workAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class);
    }

    public function productionOrderMaterial(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderMaterial::class);
    }

    public function operationRequirement(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderMaterialOperation::class, 'production_order_material_operation_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProductionMaterialTransaction::class);
    }
}
