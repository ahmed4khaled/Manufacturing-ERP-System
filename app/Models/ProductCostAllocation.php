<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostAllocation extends Model
{
    protected $fillable = ['product_cost_layer_id', 'stock_movement_id', 'reference_type', 'reference_id', 'direction', 'source_allocation_id', 'quantity', 'restored_quantity', 'unit_cost', 'total_cost', 'reversed_at'];

    protected $casts = ['quantity' => 'decimal:3', 'restored_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2', 'reversed_at' => 'datetime'];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(ProductCostLayer::class, 'product_cost_layer_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function sourceAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_allocation_id');
    }
}
