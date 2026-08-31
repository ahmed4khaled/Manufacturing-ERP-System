<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierInvoice;
use App\Services\Accounting\DocumentPostingService;
use App\Services\CodeGeneratorService;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSupplierPaymentAction
{
    public function __construct(
        private readonly SupplierPaymentAllocationService $allocations,
        private readonly DocumentPostingService $accounting,
        private readonly CodeGeneratorService $codes,
    ) {}

    public function handle(array $data)
    {
        return DB::transaction(function () use ($data) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($data['supplier_invoice_id']);
            $amount = (string) $data['amount'];

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'يجب أن يكون مبلغ الدفعة أكبر من صفر.']);
            }

            $currentPaid = $this->allocations->paidAmountForInvoice($invoice->id);
            $newPaid = bcadd($currentPaid === '' ? '0' : $currentPaid, $amount, 2);

            if (bccomp($newPaid, (string) $invoice->total, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'لا يمكن أن تتجاوز الدفعة رصيد الفاتورة المتبقي.']);
            }

            $payment = $invoice->payments()->create(collect($data)->except(['status', 'supplier_id'])->all() + [
                'payment_number' => $data['payment_number'] ?? $this->codes->next('SPAY'),
                'supplier_id' => $invoice->supplier_id,
                'created_by' => auth()->id(),
                'status' => SupplierPaymentStatus::CONFIRMED,
            ]);
            $this->allocations->persist($payment, [[
                'supplier_invoice_id' => $invoice->id,
                'amount' => $amount,
            ]]);
            $this->allocations->refreshInvoices([$invoice->id]);
            $this->accounting->supplierPayment($payment);

            return $payment;
        });
    }
}
