<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreateCustomerAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = CustomerStatus::ACTIVE;

            $customer = Customer::create($data);

            $customer->update([
                'code' => $this->codeGenerator->generate('CUS', $customer->id),
            ]);

            return $customer->fresh();
        });
    }
}
