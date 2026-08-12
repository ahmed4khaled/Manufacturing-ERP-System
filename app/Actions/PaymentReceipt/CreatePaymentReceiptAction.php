<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreatePaymentReceiptAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = PaymentReceiptStatus::DRAFT;
            $data['created_by'] = auth()->id();

            $receipt = PaymentReceipt::create($data);

            $receipt->update([
                'receipt_number' => $this->codeGenerator->next('RCPT'),
            ]);

            return $receipt->fresh(['customer', 'creator']);
        });
    }
}
