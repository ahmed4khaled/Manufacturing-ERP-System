<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use SoftDeletes;

    protected $fillable = ['order_number', 'product_id', 'product_variant_id', 'bom_id', 'product_name', 'color_name', 'size_name', 'sku', 'bom_number', 'bom_version', 'base_unit_cost', 'operations_unit_cost', 'estimated_unit_cost', 'estimated_total_cost', 'final_unit_cost', 'final_total_cost', 'planned_quantity', 'planned_start_date', 'planned_completion_date', 'status', 'notes', 'created_by', 'confirmed_by', 'held_by', 'cancelled_by', 'closed_by', 'confirmed_at', 'held_at', 'cancelled_at', 'closed_at'];

    protected $casts = ['base_unit_cost' => 'decimal:2', 'operations_unit_cost' => 'decimal:2', 'estimated_unit_cost' => 'decimal:2', 'estimated_total_cost' => 'decimal:2', 'final_unit_cost' => 'decimal:2', 'final_total_cost' => 'decimal:2', 'planned_quantity' => 'decimal:3', 'planned_start_date' => 'date', 'planned_completion_date' => 'date', 'status' => ProductionOrderStatus::class, 'confirmed_at' => 'datetime', 'held_at' => 'datetime', 'cancelled_at' => 'datetime', 'closed_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class)->orderBy('sequence');
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function materialTransactions(): HasMany
    {
        return $this->hasMany(ProductionMaterialTransaction::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(ProductionDefect::class);
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionRework::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProductionOrderActivity::class)->latest();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return blank($search) ? $query : $query->where(fn (Builder $q) => $q->where('order_number', 'like', "%{$search}%")->orWhere('product_name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }
}
