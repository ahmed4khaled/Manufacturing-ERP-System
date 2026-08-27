<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BomOperation extends Model
{
    protected $fillable = ['bom_id', 'manufacturing_operation_id', 'operation_name', 'unit_cost', 'sequence', 'notes'];

    protected $casts = ['unit_cost' => 'decimal:2', 'sequence' => 'integer'];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function manufacturingOperation(): BelongsTo
    {
        return $this->belongsTo(ManufacturingOperation::class);
    }

    public function materialAllocations(): HasMany
    {
        return $this->hasMany(BomItemOperation::class);
    }
}
