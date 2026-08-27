<?php

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Services\PaymentReceiptAllocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'discount',
        'total_amount',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'status' => SalesInvoiceStatus::class,
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentReceiptAllocation::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function getPaidAmountAttribute(): float
    {
        if (array_key_exists('paid_amount', $this->attributes)) {
            return (float) ($this->attributes['paid_amount'] ?? 0);
        }

        return (float) app(PaymentReceiptAllocationService::class)
            ->paidAmountForInvoice($this->id);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(
            0,
            (float) $this->total_amount - $this->returned_amount - $this->paid_amount
        );
    }

    public function getReturnedAmountAttribute(): float
    {
        if (array_key_exists('returned_amount', $this->attributes)) {
            return (float) ($this->attributes['returned_amount'] ?? 0);
        }

        return (float) $this->salesReturns()
            ->where('status', SalesReturnStatus::CONFIRMED)
            ->sum('total_amount');
    }

    public function getNetAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->returned_amount);
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->net_amount <= 0 || $this->remaining_amount <= 0) {
            return 'paid';
        }

        if ($this->paid_amount <= 0) {
            return 'unpaid';
        }

        return 'partially_paid';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'مسددة',
            'partially_paid' => 'مسددة جزئيًا',
            default => 'غير مسددة',
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
        return $this->belongsTo(Customer::class)->withTrashed();
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
