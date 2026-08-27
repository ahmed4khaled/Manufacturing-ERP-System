<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceiptAllocation extends Model
{
    protected $fillable = [
        'payment_receipt_id',
        'sales_invoice_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class)->withTrashed();
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class)->withTrashed();
    }
}
