<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReceiptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\SalesInvoice;
class PaymentReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'customer_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'status',
        'created_by',
        'sales_invoice_id',
'payment_type',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status' => PaymentReceiptStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query->where('receipt_number', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
        });
    }
public function invoice(): BelongsTo
{
    return $this->belongsTo(
        SalesInvoice::class,
        'sales_invoice_id'
    );
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

    public function scopeFilterDateFrom(Builder $query, ?string $dateFrom): Builder
    {
        if (blank($dateFrom)) {
            return $query;
        }

        return $query->whereDate('payment_date', '>=', $dateFrom);
    }

    public function scopeFilterDateTo(Builder $query, ?string $dateTo): Builder
    {
        if (blank($dateTo)) {
            return $query;
        }

        return $query->whereDate('payment_date', '<=', $dateTo);
    }
}
