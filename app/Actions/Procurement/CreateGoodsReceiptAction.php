<?php

namespace App\Actions\Procurement;

use App\Models\{GoodsReceipt, PurchaseOrder, PurchaseOrderItem};
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGoodsReceiptAction
{
    public function __construct(private CodeGeneratorService $codes) {}

    public function handle(array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($data['purchase_order_id']);

            if ($po->status === 'cancelled') {
                throw ValidationException::withMessages(['purchase_order_id' => 'Cancelled purchase orders cannot receive goods.']);
            }

            $items = $data['items'];

            $receipt = GoodsReceipt::create([
                'receipt_number' => $this->codes->next('GR'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'received_at' => $data['received_at'],
                'received_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

            $poItemMap = $po->items->keyBy('id');

            $poItemIds = array_map(fn($it) => $it['purchase_order_item_id'], $items);
            PurchaseOrderItem::query()->whereIn('id', $poItemIds)->lockForUpdate()->get();

            foreach ($items as $item) {
                $poItem = $poItemMap->get($item['purchase_order_item_id']);
                if (! $poItem) {
                    throw ValidationException::withMessages(['items' => 'One or more receipt items do not belong to the selected purchase order.']);
                }

                $received = (string) ($item['received_quantity'] ?? '0');
                $accepted = (string) ($item['accepted_quantity'] ?? '0');
                $rejected = (string) ($item['rejected_quantity'] ?? '0');

                if (bccomp(bcadd($accepted, $rejected, 3), $received, 3) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Accepted and rejected quantities must equal received quantity.']);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'ordered_quantity' => $poItem->quantity,
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'unit_cost' => $item['unit_cost'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $receipt->fresh('items');
        });
    }
}
