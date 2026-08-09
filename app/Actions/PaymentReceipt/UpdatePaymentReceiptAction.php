<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePaymentReceiptAction
{
    public function handle(PaymentReceipt $receipt, array $data): PaymentReceipt
    {
        if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft receipts can be edited.',
            ]);
        }

        return DB::transaction(function () use ($receipt, $data) {
            $receipt->update($data);

            return $receipt->fresh(['customer', 'creator']);
        });
    }
}
