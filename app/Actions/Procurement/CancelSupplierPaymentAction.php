<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use App\Services\Accounting\DocumentPostingService;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSupplierPaymentAction
{
    public function __construct(
        private readonly SupplierPaymentAllocationService $allocations,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(SupplierPayment $payment): SupplierPayment
    {
        return DB::transaction(function () use ($payment): SupplierPayment {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === SupplierPaymentStatus::CANCELLED) {
                return $payment;
            }
            if ($payment->status !== SupplierPaymentStatus::CONFIRMED) {
                throw ValidationException::withMessages(['status' => 'لا يمكن إلغاء إلا دفعات الموردين المؤكدة.']);
            }

            $invoiceIds = $payment->allocations()->pluck('supplier_invoice_id');
            if ($invoiceIds->isEmpty() && $payment->supplier_invoice_id) {
                $invoiceIds->push($payment->supplier_invoice_id);
            }

            $payment->update(['status' => SupplierPaymentStatus::CANCELLED]);
            $this->allocations->refreshInvoices($invoiceIds);
            $this->accounting->reverse($payment, 'supplier_payment', now()->toDateString());

            return $payment->fresh(['supplier', 'invoice', 'allocations.supplierInvoice', 'creator']);
        });
    }
}
