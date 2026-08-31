<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSupplierPaymentAction
{
    public function handle(SupplierPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($payment->status, [SupplierPaymentStatus::DRAFT, SupplierPaymentStatus::CANCELLED], true)) {
                throw ValidationException::withMessages(['status' => 'ألغِ الدفعة المؤكدة واعكس أثرها المحاسبي قبل حذفها.']);
            }
            $payment->delete();
        });
    }
}
