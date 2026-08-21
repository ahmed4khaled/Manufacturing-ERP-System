<?php

namespace App\Models;

use App\Enums\ManufacturingOperationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOperation extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'status'];

    protected $casts = ['status' => ManufacturingOperationStatus::class];

    public function bomOperations(): HasMany
    {
        return $this->hasMany(BomOperation::class);
    }

    public function productionOperations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return blank($search) ? $query : $query->where('name', 'like', "%{$search}%");
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }
}
