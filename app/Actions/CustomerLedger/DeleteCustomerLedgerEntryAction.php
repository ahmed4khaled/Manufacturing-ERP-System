<?php

namespace App\Actions\CustomerLedger;

use App\Models\CustomerLedgerEntry;
use Illuminate\Support\Facades\DB;

class DeleteCustomerLedgerEntryAction
{
    public function handle(CustomerLedgerEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->delete();
        });
    }
}
