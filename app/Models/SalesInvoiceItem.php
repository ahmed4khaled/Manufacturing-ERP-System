<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sales_invoice_id',
        'product_variant_id',
        'product_name',
        'color_name',
        'size_name',
        'sku',
        'barcode',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function salesReturnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
