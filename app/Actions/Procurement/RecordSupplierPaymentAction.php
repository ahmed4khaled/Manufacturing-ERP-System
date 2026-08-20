<?php

namespace App\Actions\Procurement;

use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSupplierPaymentAction
{
    public function handle(array $data)
    {
        return DB::transaction(function () use ($data) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($data['supplier_invoice_id']);
            $amount = (string) $data['amount'];

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'يجب أن يكون مبلغ الدفعة أكبر من صفر.']);
            }

            $currentPaid = (string) $invoice->payments()->sum('amount');
            $newPaid = bcadd($currentPaid === '' ? '0' : $currentPaid, $amount, 2);

            if (bccomp($newPaid, (string) $invoice->total, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'لا يمكن أن تتجاوز الدفعة رصيد الفاتورة المتبقي.']);
            }

            $payment = $invoice->payments()->create($data + ['supplier_id' => $invoice->supplier_id, 'created_by' => auth()->id()]);

            $remaining = bcsub((string) $invoice->total, $newPaid, 2);
            $status = bccomp($remaining, '0', 2) <= 0 ? 'paid' : (bccomp($newPaid, '0', 2) > 0 ? 'partially_paid' : 'unpaid');

            $invoice->update(['paid_amount' => $newPaid, 'remaining_amount' => $remaining, 'status' => $status]);

            return $payment;
        });
    }
}
