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
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $balance = $customer->ledgerEntries()
                ->selectRaw(
                    'COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance'
                )
                ->value('balance');

            $balance = (float) $balance;

            if (abs($balance) > 0.001) {
                throw ValidationException::withMessages([
                    'customer' => 'لا يمكن حذف العميل لأنه عليه رصيد مستحق.',
                ]);
            }
            $customer->delete();
        });
    }
}
