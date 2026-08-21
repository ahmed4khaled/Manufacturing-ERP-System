<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomOperation extends Model
{
    protected $fillable = ['bom_id', 'manufacturing_operation_id', 'operation_name', 'sequence', 'notes'];

    protected $casts = ['sequence' => 'integer'];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function manufacturingOperation(): BelongsTo
    {
        return $this->belongsTo(ManufacturingOperation::class);
    }
}
