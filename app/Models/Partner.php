<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['code', 'name', 'profit_share', 'capital_account_id', 'current_account_id', 'withdrawals_account_id', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['profit_share' => 'decimal:4', 'is_active' => 'boolean'];
    }
}
