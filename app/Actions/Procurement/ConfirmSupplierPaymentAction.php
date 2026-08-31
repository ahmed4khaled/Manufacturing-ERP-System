<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use App\Services\Accounting\DocumentPostingService;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSupplierPaymentAction
{
    public function __construct(
        private readonly SupplierPaymentAllocationService $allocations,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(SupplierPayment $payment): SupplierPayment
    {
        return DB::transaction(function () use ($payment): SupplierPayment {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === SupplierPaymentStatus::CONFIRMED) {
                return $payment;
            }
            if ($payment->status !== SupplierPaymentStatus::DRAFT) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تأكيد إلا دفعات الموردين المسودة.']);
            }
            if (bccomp((string) $payment->amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'يجب أن يكون مبلغ الدفعة أكبر من صفر.']);
            }

            $allocationRows = $this->allocations->validatePayment($payment);
            $payment->update(['status' => SupplierPaymentStatus::CONFIRMED]);
            $this->allocations->refreshInvoices(collect($allocationRows)->pluck('supplier_invoice_id'));
            $this->accounting->supplierPayment($payment);

            return $payment->fresh(['supplier', 'invoice', 'allocations.supplierInvoice', 'creator']);
        });
    }
}
