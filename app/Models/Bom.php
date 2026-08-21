<?php

namespace App\Models;

use App\Enums\BomStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use SoftDeletes;

    protected $fillable = ['bom_number', 'version', 'product_id', 'product_variant_id', 'basis_quantity', 'status', 'notes', 'created_by', 'activated_by', 'activated_at'];

    protected $casts = ['basis_quantity' => 'decimal:3', 'status' => BomStatus::class, 'activated_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(BomOperation::class)->orderBy('sequence');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return blank($search) ? $query : $query->where(fn (Builder $q) => $q->where('bom_number', 'like', "%{$search}%")->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$search}%"))->orWhereHas('productVariant', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%")));
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }
}
