<?php

namespace App\Actions\CustomerLedger;

use App\Models\CustomerLedgerEntry;
use Illuminate\Support\Facades\DB;

class CreateCustomerLedgerEntryAction
{
    public function handle(array $data): CustomerLedgerEntry
    {
        return DB::transaction(function () use ($data) {
            return CustomerLedgerEntry::create($data);
        });
    }
}
