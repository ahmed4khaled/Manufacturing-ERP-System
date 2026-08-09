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
        if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft receipts can be deleted.',
            ]);
        }

        DB::transaction(function () use ($receipt) {
            $receipt->delete();
        });
    }
}
