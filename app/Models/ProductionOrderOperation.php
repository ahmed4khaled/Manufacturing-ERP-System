<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderOperation extends Model
{
    protected $fillable = ['production_order_id', 'bom_operation_id', 'manufacturing_operation_id', 'operation_name', 'sequence', 'notes'];

    protected $casts = ['sequence' => 'integer'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function bomOperation(): BelongsTo
    {
        return $this->belongsTo(BomOperation::class);
    }

    public function manufacturingOperation(): BelongsTo
    {
        return $this->belongsTo(ManufacturingOperation::class);
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionRework::class);
    }
}
