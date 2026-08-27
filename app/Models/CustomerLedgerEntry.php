<?php

namespace App\Models;

use App\Enums\LedgerReferenceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'reference_type',
        'reference_id',
        'reference_number',
        'transaction_date',
        'description',
        'debit',
        'credit',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'reference_type' => LedgerReferenceType::class,
        'transaction_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
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
            $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
        });
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

        return $query->whereDate('transaction_date', '>=', $dateFrom);
    }

    public function scopeFilterDateTo(Builder $query, ?string $dateTo): Builder
    {
        if (blank($dateTo)) {
            return $query;
        }

        return $query->whereDate('transaction_date', '<=', $dateTo);
    }
}
