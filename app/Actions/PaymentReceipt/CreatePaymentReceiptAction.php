<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use App\Services\CodeGeneratorService;
use App\Services\PaymentReceiptInvoiceService;
use Illuminate\Support\Facades\DB;

class CreatePaymentReceiptAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly PaymentReceiptInvoiceService $invoiceService,
    ) {}

    public function handle(array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($data) {

            $data['status'] = PaymentReceiptStatus::DRAFT;
            $data['created_by'] = auth()->id();

            if (($data['payment_type'] ?? 'invoice') === 'advance') {
                $data['sales_invoice_id'] = null;
            }

            $this->invoiceService->validate($data);

            /*
            |--------------------------------------------------------------------------
            | Create Receipt
            |--------------------------------------------------------------------------
            */

            $receipt = PaymentReceipt::create($data);

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
                'creator',
            ]);
        });
    }
}
