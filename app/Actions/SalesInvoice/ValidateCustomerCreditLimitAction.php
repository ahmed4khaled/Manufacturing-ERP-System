<?php

namespace App\Actions\SalesInvoice;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class ValidateCustomerCreditLimitAction
{
    public function handle(Customer $customer, string $newAmount): void
    {
        $currentOutstanding = $customer->salesInvoices()
            ->whereIn('status', [
                \App\Enums\SalesInvoiceStatus::DRAFT,
                \App\Enums\SalesInvoiceStatus::CONFIRMED,
            ])
            ->sum('total_amount');

        $projectedAmount = bcadd(
            (string) $currentOutstanding,
            $newAmount,
            2
        );

        if (bccomp(
            $projectedAmount,
            (string) $customer->credit_limit,
            2
        ) > 0) {
            throw ValidationException::withMessages([
                'customer_id' => 'تم تجاوز حد الائتمان للعميل المحدد.',
            ]);
        }
    }
}