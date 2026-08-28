<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCostLayer extends Model
{
    protected $fillable = ['product_variant_id', 'production_order_id', 'production_output_id', 'stock_movement_id', 'layer_date', 'original_quantity', 'remaining_quantity', 'unit_cost', 'total_original_cost', 'is_final'];

    protected $casts = ['layer_date' => 'date', 'original_quantity' => 'decimal:3', 'remaining_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_original_cost' => 'decimal:2', 'is_final' => 'boolean'];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productionOutput(): BelongsTo
    {
        return $this->belongsTo(ProductionOutput::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductCostAllocation::class);
    }
}
