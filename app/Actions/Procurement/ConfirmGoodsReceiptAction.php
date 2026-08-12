<?php

namespace App\Actions\Procurement;

use App\Models\{GoodsReceipt, StockMovement, PurchaseOrderItem, PurchaseOrder};
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmGoodsReceiptAction
{
    public function __construct(private StockService $stock) {}
    public function handle(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== 'draft') throw ValidationException::withMessages(['status' => 'Only draft receipts can be confirmed.']);

            // lock related purchase order and its items to prevent concurrent receipts
            $po = PurchaseOrder::query()->lockForUpdate()->find($receipt->purchase_order_id);
            $receipt->load('items.purchaseOrderItem');
            $poItemIds = $receipt->items->pluck('purchase_order_item_id')->filter()->unique()->values()->all();
            if (! empty($poItemIds)) {
                PurchaseOrderItem::query()->whereIn('id', $poItemIds)->lockForUpdate()->get();
            }

            if (StockMovement::where('reference_type', GoodsReceipt::class)->where('reference_id', $receipt->id)->exists()) throw ValidationException::withMessages(['status' => 'Receipt inventory is already posted.']);

            // validate items belong to PO and quantities
            foreach ($receipt->items as $item) {
                if (! $item->purchaseOrderItem || $item->purchaseOrderItem->purchase_order_id !== $receipt->purchase_order_id) {
                    throw ValidationException::withMessages(['items' => 'Receipt items must belong to the referenced purchase order.']);
                }

                // compute total previously received for this PO item (confirmed receipts)
                $received = (string) $item->purchaseOrderItem->receiptItems()->whereHas('receipt', fn($q) => $q->where('status', 'confirmed'))->sum('received_quantity');
                $newReceived = bcadd($received, (string) $item->received_quantity, 3);
                $ordered = (string) $item->ordered_quantity;

                if (bccomp($newReceived, $ordered, 3) > 0) {
                    throw ValidationException::withMessages(['items' => 'Received quantity exceeds remaining PO quantity.']);
                }

                $acceptedPlusRejected = bcadd((string) $item->accepted_quantity, (string) $item->rejected_quantity, 3);
                if (bccomp($acceptedPlusRejected, (string) $item->received_quantity, 3) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Accepted and rejected quantities must equal received quantity.']);
                }
            }

            // mark confirmed and post stock IN for accepted quantities only; guard against double posting
            if (StockMovement::where('reference_type', GoodsReceipt::class)->where('reference_id', $receipt->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'Receipt inventory is already posted.']);
            }

            $receipt->update(['status' => 'confirmed']);
            $this->stock->createPurchaseReceiptMovements($receipt);

            if ($po) {
                $all = $po->items()->get()->every(function ($i) {
                    $received = (string) $i->receiptItems()->whereHas('receipt', fn($q) => $q->where('status', 'confirmed'))->sum('received_quantity');
                    return bccomp($received, (string) $i->quantity, 3) >= 0;
                });
                $po->update(['status' => $all ? 'fully_received' : 'partially_received']);
            }

            return $receipt->fresh('items');
        });
    }
}
