<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = ['code', 'name', 'profit_share', 'capital_account_id', 'current_account_id', 'withdrawals_account_id', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['profit_share' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    public function paidExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'paid_by_partner_id');
    }
}
