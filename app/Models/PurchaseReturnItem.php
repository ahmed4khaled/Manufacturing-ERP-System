<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id',
        'material_id',
        'quantity',
        'unit_cost',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }
}
