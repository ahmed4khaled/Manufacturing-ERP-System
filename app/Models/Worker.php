<?php

namespace App\Models;

use App\Enums\WorkerStatus;
use App\Enums\WorkerWagePaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'phone', 'specialization', 'notes', 'status'];

    protected $casts = ['status' => WorkerStatus::class];

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function materialTransactions(): HasMany
    {
        return $this->hasMany(ProductionMaterialTransaction::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(ProductionDefect::class);
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionRework::class);
    }

    public function productionWages(): HasMany
    {
        return $this->hasMany(ProductionWorkerWage::class);
    }

    public function wagePayments(): HasMany
    {
        return $this->hasMany(WorkerWagePayment::class);
    }

    public function latestWagePayment(): HasOne
    {
        return $this->hasOne(WorkerWagePayment::class)
            ->where('status', WorkerWagePaymentStatus::POSTED)
            ->latestOfMany('payment_date');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return blank($search) ? $query : $query->where(fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('specialization', 'like', "%{$search}%"));
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }
}
