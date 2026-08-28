<?php

namespace App\Models;

use App\Enums\MaterialCategory;
use App\Enums\MaterialStatus;
use App\Enums\MaterialUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'minimum_stock',
        'quantity',
        'standard_unit_cost',
        'description',
        'status',
    ];

    protected $casts = [
        'category' => MaterialCategory::class,
        'unit' => MaterialUnit::class,
        'minimum_stock' => 'decimal:3',
        'quantity' => 'decimal:3',
        'standard_unit_cost' => 'decimal:4',
        'status' => MaterialStatus::class,
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(MaterialStockMovement::class);
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function productionOrderMaterials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    public function productionMaterialTransactions(): HasMany
    {
        return $this->hasMany(ProductionMaterialTransaction::class);
    }

    public function costLayers(): HasMany
    {
        return $this->hasMany(MaterialCostLayer::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeFilterCategory(Builder $query, ?string $category): Builder
    {
        if (blank($category)) {
            return $query;
        }

        return $query->where('category', $category);
    }
}
