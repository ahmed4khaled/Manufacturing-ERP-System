<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePaymentReceiptAction
{
    public function __construct(
        private readonly PaymentReceiptInvoiceService $invoiceService,
    ) {}

    public function handle(PaymentReceipt $receipt, array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt, $data) {
            $receipt = PaymentReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعديل إلا السندات المسودة.',
                ]);
            }

            if (($data['payment_type'] ?? 'invoice') === 'advance') {
                $data['sales_invoice_id'] = null;
            }

            $this->invoiceService->validate($data, $receipt->id);
            $receipt->update($data);

            return $receipt->fresh(['customer', 'invoice', 'creator']);
        });
    }
}
