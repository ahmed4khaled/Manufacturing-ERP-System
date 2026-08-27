<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItemOperation extends Model
{
    protected $fillable = ['bom_item_id', 'bom_operation_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    public function bomOperation(): BelongsTo
    {
        return $this->belongsTo(BomOperation::class);
    }
}
