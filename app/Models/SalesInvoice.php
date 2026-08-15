<?php

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PaymentReceipt;
use App\Enums\PaymentReceiptStatus;
class SalesInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'invoice_date',
        'notes',
        'status',
        'total_quantity',
        'subtotal',
        'total_amount',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'status' => SalesInvoiceStatus::class,
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_quantity' => 'decimal:3',
    ];
    public function paymentReceipts(): HasMany
{
    return $this->hasMany(
        PaymentReceipt::class,
        'sales_invoice_id'
    );
}

public function getPaidAmountAttribute(): float
{
    if (array_key_exists('paid_amount', $this->attributes)) {
        return (float) ($this->attributes['paid_amount'] ?? 0);
    }

    return (float) $this->paymentReceipts()
        ->where(
            'status',
            PaymentReceiptStatus::CONFIRMED
        )
        ->sum('amount');
}

public function getRemainingAmountAttribute(): float
{
    return max(
        0,
        (float) $this->total_amount - $this->paid_amount
    );
}

public function getPaymentStatusAttribute(): string
{
    if ($this->paid_amount <= 0) {
        return 'unpaid';
    }

    if ($this->remaining_amount <= 0) {
        return 'paid';
    }

    return 'partially_paid';
}

public function getPaymentStatusLabelAttribute(): string
{
    return match ($this->payment_status) {
        'paid' => 'Paid',
        'partially_paid' => 'Partially Paid',
        default => 'Unpaid',
    };
}

public function getPaymentStatusBadgeClassAttribute(): string
{
    return match ($this->payment_status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        default => 'danger',
    };
}

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
        });
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeFilterCustomer(Builder $query, ?int $customerId): Builder
    {
        if (blank($customerId)) {
            return $query;
        }

        return $query->where('customer_id', $customerId);
    }
}
