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
                throw ValidationException::withMessages(['status' => 'Cancelled purchase orders cannot be approved.']);
            }

            if ($po->status === 'approved') {
                throw ValidationException::withMessages(['status' => 'This purchase order has already been approved.']);
            }

            if ($po->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft purchase orders can be approved.']);
            }

            // Validate has items
            if ($po->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Purchase order must have at least one item.']);
            }

            // Validate supplier
            if (! $po->supplier || $po->supplier->trashed()) {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier is invalid or has been deleted.']);
            }

            // Validate all items have valid active materials
            foreach ($po->items as $item) {
                if (! $item->material) {
                    throw ValidationException::withMessages(['items' => 'One or more materials have been deleted.']);
                }

                if ($item->material->trashed()) {
                    throw ValidationException::withMessages(['items' => 'Material ' . $item->material->name . ' has been deleted.']);
                }

                if ($item->material->status !== MaterialStatus::ACTIVE) {
                    throw ValidationException::withMessages(['items' => 'Material ' . $item->material->name . ' is not active.']);
                }
            }

            // Approve the purchase order
            $po->update(['status' => 'approved']);

            return $po->fresh('items.material', 'supplier');
        });
    }
}
