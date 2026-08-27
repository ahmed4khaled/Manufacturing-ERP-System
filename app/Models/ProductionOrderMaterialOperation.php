<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderMaterialOperation extends Model
{
    protected $fillable = [
        'production_order_material_id',
        'production_order_operation_id',
        'standard_quantity',
        'required_quantity',
    ];

    protected $casts = [
        'standard_quantity' => 'decimal:3',
        'required_quantity' => 'decimal:3',
    ];

    public function productionOrderMaterial(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderMaterial::class);
    }

    public function productionOrderOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class);
    }

    public function assignmentMaterials(): HasMany
    {
        return $this->hasMany(WorkAssignmentMaterial::class);
    }
}
