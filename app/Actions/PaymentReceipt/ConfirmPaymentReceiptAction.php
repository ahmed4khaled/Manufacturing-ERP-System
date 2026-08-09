<?php

namespace App\Actions\PaymentReceipt;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPaymentReceiptAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $createLedgerEntry,
    ) {}

    public function handle(PaymentReceipt $receipt): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt->refresh();

            if ($receipt->status === PaymentReceiptStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'This receipt is already confirmed.',
                ]);
            }

            if ($receipt->status === PaymentReceiptStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled receipts cannot be confirmed.',
                ]);
            }

            if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft receipts can be confirmed.',
                ]);
            }

            if (! Customer::query()->whereKey($receipt->customer_id)->exists()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'The selected customer does not exist.',
                ]);
            }

            if ((float) $receipt->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot confirm a receipt with zero or negative amount.',
                ]);
            }

            $alreadyPosted = CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
                ->where('reference_id', $receipt->id)
                ->exists();

            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'status' => 'A ledger entry already exists for this receipt.',
                ]);
            }

            $receipt->update(['status' => PaymentReceiptStatus::CONFIRMED]);

            $this->createLedgerEntry->handle([
                'customer_id' => $receipt->customer_id,
                'reference_type' => LedgerReferenceType::PAYMENT_RECEIPT,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'transaction_date' => $receipt->payment_date,
                'description' => 'Customer Payment Receipt',
                'debit' => 0,
                'credit' => $receipt->amount,
                'created_by' => auth()->id(),
            ]);

            return $receipt->fresh(['customer', 'creator']);
        });
    }
}
