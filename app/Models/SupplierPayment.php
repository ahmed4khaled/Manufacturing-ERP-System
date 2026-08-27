<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'purchase_order_id',
        'supplier_invoice_id',
        'payment_type',
        'payment_date',
        'amount',
        'payment_method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Supplier
    |--------------------------------------------------------------------------
    */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class,
            'supplier_id'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase Order
    |--------------------------------------------------------------------------
    */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class,
            'purchase_order_id'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Supplier Invoice
    |--------------------------------------------------------------------------
    */

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
            'supplier_invoice_id'
        )->withTrashed();
    }
}
