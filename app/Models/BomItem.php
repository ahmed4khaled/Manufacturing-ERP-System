<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BomItem extends Model
{
    protected $fillable = ['bom_id', 'material_id', 'material_code', 'material_name', 'unit', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function operationAllocations(): HasMany
    {
        return $this->hasMany(BomItemOperation::class);
    }
}
