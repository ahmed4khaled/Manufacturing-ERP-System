<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use App\Services\CodeGeneratorService;
use App\Services\PaymentReceiptAllocationService;
use Illuminate\Support\Facades\DB;

class CreatePaymentReceiptAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly PaymentReceiptAllocationService $allocationService,
    ) {}

    public function handle(array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($data) {

            $data['status'] = PaymentReceiptStatus::DRAFT;
            $data['created_by'] = auth()->id();

            if (($data['payment_type'] ?? 'invoice') === 'advance') {
                $data['sales_invoice_id'] = null;
            }

            $allocations = $this->allocationService->validate($data);
            $attributes = collect($data)->except('allocations')->all();
            $attributes['sales_invoice_id'] = count($allocations) === 1
                ? $allocations[0]['sales_invoice_id']
                : null;

            /*
            |--------------------------------------------------------------------------
            | Create Receipt
            |--------------------------------------------------------------------------
            */

            $receipt = PaymentReceipt::create($attributes);

            $this->allocationService->persist($receipt, $allocations);

            /*
            |--------------------------------------------------------------------------
            | Generate Receipt Number
            |--------------------------------------------------------------------------
            */

            $receipt->update([
                'receipt_number' => $this->codeGenerator->next('RCPT'),
            ]);

            return $receipt->fresh([
                'customer',
                'invoice',
                'allocations.salesInvoice',
                'creator',
            ]);
        });
    }
}
