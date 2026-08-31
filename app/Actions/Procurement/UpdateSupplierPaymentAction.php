<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSupplierPaymentAction
{
    public function __construct(private readonly SupplierPaymentAllocationService $allocations) {}

    public function handle(SupplierPayment $payment, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $data): SupplierPayment {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SupplierPaymentStatus::DRAFT) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تعديل إلا دفعات الموردين المسودة.']);
            }

            $purchaseOrderId = $data['purchase_order_id'] ?? null;
            if (($data['payment_type'] ?? 'invoice') === 'advance' && $purchaseOrderId) {
                $order = PurchaseOrder::query()->findOrFail($purchaseOrderId);
                if ((int) $order->supplier_id !== (int) $data['supplier_id']) {
                    throw ValidationException::withMessages(['purchase_order_id' => 'أمر الشراء لا يخص المورد المحدد.']);
                }
            }
            if (($data['payment_type'] ?? 'invoice') === 'invoice') {
                $purchaseOrderId = null;
            }

            $allocationRows = $this->allocations->validate($data, $payment->id);
            $payment->update([
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $purchaseOrderId,
                'payment_type' => $data['payment_type'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->allocations->persist($payment, $allocationRows);

            return $payment->fresh(['supplier', 'invoice', 'allocations.supplierInvoice', 'creator']);
        });
    }
}
