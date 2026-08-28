<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCostLayer extends Model
{
    protected $fillable = ['material_id', 'goods_receipt_id', 'goods_receipt_item_id', 'material_stock_movement_id', 'layer_date', 'original_quantity', 'remaining_quantity', 'unit_cost', 'total_original_cost'];

    protected $casts = ['layer_date' => 'date', 'original_quantity' => 'decimal:3', 'remaining_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_original_cost' => 'decimal:2'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class)->withTrashed();
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(MaterialStockMovement::class, 'material_stock_movement_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MaterialCostAllocation::class);
    }
}
