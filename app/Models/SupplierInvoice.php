<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['invoice_number', 'supplier_reference', 'supplier_id', 'purchase_order_id', 'goods_receipt_id', 'receipt_mode', 'submission_token', 'invoice_date', 'due_date', 'subtotal', 'discount', 'tax', 'total', 'paid_amount', 'remaining_amount', 'status', 'notes'];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        if (bccomp((string) $this->remaining_amount, '0.00', 2) <= 0) {
            return 'مسددة';
        }

        return bccomp((string) $this->paid_amount, '0.00', 2) > 0 ? 'مسددة جزئيًا' : 'غير مسددة';
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        if (bccomp((string) $this->remaining_amount, '0.00', 2) <= 0) {
            return 'success';
        }

        return bccomp((string) $this->paid_amount, '0.00', 2) > 0 ? 'warning' : 'secondary';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class)->withTrashed();
    }
}
