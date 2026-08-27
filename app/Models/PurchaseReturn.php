<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;

    protected $fillable = ['return_number', 'supplier_id', 'purchase_order_id', 'goods_receipt_id', 'return_date', 'reason', 'status', 'notes', 'created_by'];

    protected $casts = ['return_date' => 'date'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
