<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderMaterial extends Model
{
    protected $fillable = ['production_order_id', 'bom_item_id', 'material_id', 'material_code', 'material_name', 'unit', 'standard_quantity', 'required_quantity', 'is_overridden', 'override_reason', 'overridden_by'];

    protected $casts = ['standard_quantity' => 'decimal:3', 'required_quantity' => 'decimal:3', 'is_overridden' => 'boolean'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function assignmentMaterials(): HasMany
    {
        return $this->hasMany(WorkAssignmentMaterial::class);
    }
}
