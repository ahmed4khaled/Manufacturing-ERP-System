<?php

namespace App\Actions\Procurement;

use App\Enums\SupplierPaymentStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CodeGeneratorService;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSupplierPaymentAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly SupplierPaymentAllocationService $allocations,
    ) {}

    public function handle(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data): SupplierPayment {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $purchaseOrderId = $data['purchase_order_id'] ?? null;

            if (($data['payment_type'] ?? 'invoice') === 'advance' && $purchaseOrderId) {
                $order = PurchaseOrder::query()->findOrFail($purchaseOrderId);
                if ((int) $order->supplier_id !== (int) $supplier->id) {
                    throw ValidationException::withMessages(['purchase_order_id' => 'أمر الشراء لا يخص المورد المحدد.']);
                }
            }

            if (($data['payment_type'] ?? 'invoice') === 'invoice') {
                $purchaseOrderId = null;
            }

            $allocationRows = $this->allocations->validate($data);
            $payment = SupplierPayment::query()->create([
                'payment_number' => $this->codes->next('SPAY'),
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_invoice_id' => null,
                'payment_type' => $data['payment_type'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => SupplierPaymentStatus::DRAFT,
                'created_by' => auth()->id(),
            ]);

            $this->allocations->persist($payment, $allocationRows);

            return $payment->fresh(['supplier', 'invoice', 'allocations.supplierInvoice', 'creator']);
        });
    }
}
