<?php

namespace App\Actions\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCustomerAction
{
    public function handle(Customer $customer): void
    {

        DB::transaction(function () use ($customer) {

            $balance = $customer->ledgerEntries()
                ->selectRaw(
                    'COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance'
                )
                ->value('balance');

            $balance = (float) $balance;

            if ($balance > 0.001) {
                throw ValidationException::withMessages([
                    'customer' => sprintf(
                        'Cannot delete this customer because they have an outstanding balance of %.2f EGP.',
                        $balance
                    ),
                ]);
            }
            $customer->delete();
        });
    }
}