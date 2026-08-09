<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPaymentReceiptAction
{
    public function handle(PaymentReceipt $receipt): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt->refresh();

            if ($receipt->status === PaymentReceiptStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This receipt is already cancelled.',
                ]);
            }

            if ($receipt->status !== PaymentReceiptStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'Only confirmed receipts can be cancelled.',
                ]);
            }

            $receipt->update(['status' => PaymentReceiptStatus::CANCELLED]);

            // TODO: Future — create a reversal ledger entry (debit) to offset the original payment receipt credit.

            return $receipt->fresh(['customer', 'creator']);
        });
    }
}
