<?php

namespace App\Models;

use App\Enums\SalesReturnStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'return_number',
        'sales_invoice_id',
        'customer_id',
        'return_date',
        'status',
        'reason',
        'notes',
        'total_quantity',
        'total_amount',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'status' => SalesReturnStatus::class,
        'total_quantity' => 'decimal:3',
        'total_amount' => 'decimal:2',
    ];

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query->where('return_number', 'like', "%{$search}%")
                ->orWhereHas('salesInvoice', fn (Builder $invoice) => $invoice
                    ->where('invoice_number', 'like', "%{$search}%"))
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
        });
    }
}
