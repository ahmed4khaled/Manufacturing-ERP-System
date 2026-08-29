<?php

namespace App\Actions\PaymentReceipt;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Services\Accounting\DocumentPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPaymentReceiptAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $ledger,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(PaymentReceipt $receipt): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = PaymentReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status === PaymentReceiptStatus::CANCELLED) {
                return $receipt->load(['customer', 'invoice', 'allocations.salesInvoice', 'creator']);
            }

            if ($receipt->status !== PaymentReceiptStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء إلا السندات المؤكدة.',
                ]);
            }

            if (CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT_REVERSAL)
                ->where('reference_id', $receipt->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'تم تسجيل عكس لهذا السند بالفعل.',
                ]);
            }

            $receipt->update(['status' => PaymentReceiptStatus::CANCELLED]);

            $this->ledger->handle([
                'customer_id' => $receipt->customer_id,
                'reference_type' => LedgerReferenceType::PAYMENT_RECEIPT_REVERSAL,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'transaction_date' => now()->toDateString(),
                'description' => 'إلغاء سند قبض',
                'debit' => $receipt->amount,
                'credit' => 0,
                'created_by' => auth()->id(),
            ]);
            $this->accounting->reverse($receipt, 'customer_payment', now()->toDateString());

            return $receipt->fresh(['customer', 'invoice', 'allocations.salesInvoice', 'creator']);
        });
    }
}
