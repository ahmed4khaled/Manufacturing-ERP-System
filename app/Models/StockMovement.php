<?php

namespace App\Models;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'movement_type',
        'direction',
        'quantity',
        'reference_type',
        'reference_id',
        'reference_number',
        'movement_date',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'movement_type' => StockMovementType::class,
        'direction' => StockMovementDirection::class,
        'quantity' => 'integer',
        'movement_date' => 'date',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where('reference_number', 'like', "%{$search}%");
    }

    public function scopeFilterProduct(Builder $query, ?int $productId): Builder
    {
        if (blank($productId)) {
            return $query;
        }

        return $query->whereHas('productVariant', fn (Builder $q) => $q->where('product_id', $productId));
    }

    public function scopeFilterVariant(Builder $query, ?int $variantId): Builder
    {
        return blank($variantId) ? $query : $query->where('product_variant_id', $variantId);
    }

    public function scopeFilterMovementType(Builder $query, ?string $movementType): Builder
    {
        return blank($movementType) ? $query : $query->where('movement_type', $movementType);
    }

    public function scopeFilterDirection(Builder $query, ?string $direction): Builder
    {
        return blank($direction) ? $query : $query->where('direction', $direction);
    }

    public function scopeFilterDateFrom(Builder $query, ?string $dateFrom): Builder
    {
        return blank($dateFrom) ? $query : $query->whereDate('movement_date', '>=', $dateFrom);
    }

    public function scopeFilterDateTo(Builder $query, ?string $dateTo): Builder
    {
        return blank($dateTo) ? $query : $query->whereDate('movement_date', '<=', $dateTo);
    }
}
