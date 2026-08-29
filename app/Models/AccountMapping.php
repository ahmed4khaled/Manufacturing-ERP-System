<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    protected $fillable = ['key', 'account_id', 'description'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
