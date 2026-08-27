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

            if (! in_array($receipt->status, [PaymentReceiptStatus::DRAFT, PaymentReceiptStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف سند قبض مؤكد مباشرة. ألغِ السند واعكس أثره أولاً.',
                ]);
            }

            $receipt->delete();
        });
    }
}
