<?php

namespace App\Actions\Procurement;

use App\Enums\MaterialStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePurchaseOrderAction
{
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->with('items.material', 'supplier')
                ->findOrFail($purchaseOrder->id);

            // Validate status
            if ($po->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'لا يمكن اعتماد أمر شراء ملغي.']);
            }

            if ($po->status === 'approved') {
                throw ValidationException::withMessages(['status' => 'تم اعتماد أمر الشراء هذا بالفعل.']);
            }

            if ($po->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'لا يمكن اعتماد إلا أوامر الشراء المسودة.']);
            }

            // Validate has items
            if ($po->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'يجب أن يحتوي أمر الشراء على بند واحد على الأقل.']);
            }

            // Validate supplier
            if (! $po->supplier || $po->supplier->trashed()) {
                throw ValidationException::withMessages(['supplier_id' => 'المورد غير صالح أو تم حذفه.']);
            }

            // Validate all items have valid active materials
            foreach ($po->items as $item) {
                if (! $item->material) {
                    throw ValidationException::withMessages(['items' => 'تم حذف خامة واحدة أو أكثر من البنود.']);
                }

                if ($item->material->trashed()) {
                    throw ValidationException::withMessages(['items' => 'تم حذف الخامة «' . $item->material->name . '».']);
                }

                if ($item->material->status !== MaterialStatus::ACTIVE) {
                    throw ValidationException::withMessages(['items' => 'الخامة «' . $item->material->name . '» غير نشطة.']);
                }
            }

            // Approve the purchase order
            $po->update(['status' => 'approved']);

            return $po->fresh('items.material', 'supplier');
        });
    }
}
