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
                        'لا يمكن حذف هذا العميل لوجود رصيد مستحق بقيمة %.2f ج.م.',
                        $balance
                    ),
                ]);
            }
            $customer->delete();
        });
    }
}