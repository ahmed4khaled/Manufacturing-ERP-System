<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePaymentReceiptAction
{
    public function handle(PaymentReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $receipt = PaymentReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف إلا السندات المسودة.',
                ]);
            }

            $receipt->delete();
        });
    }
}
