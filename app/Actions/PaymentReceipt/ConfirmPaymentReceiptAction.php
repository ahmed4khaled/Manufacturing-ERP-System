<?php

namespace App\Actions\PaymentReceipt;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Services\Accounting\DocumentPostingService;
use App\Services\PaymentReceiptAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPaymentReceiptAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $createLedgerEntry,
        private readonly PaymentReceiptAllocationService $allocationService,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(PaymentReceipt $receipt): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = PaymentReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status === PaymentReceiptStatus::CONFIRMED) {
                return $receipt->load(['customer', 'invoice', 'allocations.salesInvoice', 'creator']);
            }

            if ($receipt->status === PaymentReceiptStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد سند ملغي.',
                ]);
            }

            if ($receipt->status !== PaymentReceiptStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد إلا السندات المسودة.',
                ]);
            }

            if (! Customer::query()->whereKey($receipt->customer_id)->exists()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'العميل المحدد غير موجود.',
                ]);
            }

            if ((float) $receipt->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'لا يمكن تأكيد سند بمبلغ صفر أو سالب.',
                ]);
            }

            $this->allocationService->validateReceipt($receipt);

            $alreadyPosted = CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
                ->where('reference_id', $receipt->id)
                ->exists();

            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'status' => 'يوجد قيد حساب مسجل بالفعل لهذا السند.',
                ]);
            }

            $receipt->update(['status' => PaymentReceiptStatus::CONFIRMED]);

            $this->createLedgerEntry->handle([
                'customer_id' => $receipt->customer_id,
                'reference_type' => LedgerReferenceType::PAYMENT_RECEIPT,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'transaction_date' => $receipt->payment_date,
                'description' => 'سند قبض عميل',
                'debit' => 0,
                'credit' => $receipt->amount,
                'created_by' => auth()->id(),
            ]);
            $this->accounting->customerPayment($receipt);

            return $receipt->fresh(['customer', 'invoice', 'allocations.salesInvoice', 'creator']);
        });
    }
}
