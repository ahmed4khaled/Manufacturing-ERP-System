<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'phone_2',
        'city',
        'address',
        'credit_limit',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'status' => CustomerStatus::class,
    ];

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('phone_2', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
        });
    }
}
