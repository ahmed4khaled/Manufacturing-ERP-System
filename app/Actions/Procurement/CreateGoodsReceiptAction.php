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

            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($data['purchase_order_id']);

            if ($po->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'لا يمكن استلام خامات على أمر شراء ملغي.'
                ]);
            }

            $items = $data['items'];

            $poItemMap = $po->items->keyBy('id');

            $poItemIds = array_map(
                fn ($it) => $it['purchase_order_item_id'],
                $items
            );

            PurchaseOrderItem::query()
                ->whereIn('id', $poItemIds)
                ->lockForUpdate()
                ->get();

            /*
             * Create receipt first
             */
            $receipt = GoodsReceipt::create([
                'receipt_number' => $this->codes->next('GR'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'received_at' => $data['received_at'],
                'received_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($items as $item) {

                $poItem = $poItemMap->get($item['purchase_order_item_id']);

                if (! $poItem) {
                    throw ValidationException::withMessages([
                        'items' => 'بند واحد أو أكثر من إذن الاستلام لا يتبع أمر الشراء المحدد.'
                    ]);
                }

                /*
                 * IMPORTANT:
                 * Remaining quantity is based on ACCEPTED quantity.
                 *
                 * Rejected quantity does NOT fulfill the PO.
                 */
                $acceptedPreviously = (string) $poItem->receiptItems()
                    ->whereHas(
                        'receipt',
                        fn ($q) => $q->where('status', 'confirmed')
                    )
                    ->sum('accepted_quantity');

                $ordered = (string) $poItem->quantity;

                $remaining = bcsub(
                    $ordered,
                    $acceptedPreviously,
                    3
                );

                if (bccomp($remaining, '0', 3) < 0) {
                    $remaining = '0';
                }

                $received = (string) ($item['received_quantity'] ?? '0');
                $accepted = (string) ($item['accepted_quantity'] ?? '0');
                $rejected = (string) ($item['rejected_quantity'] ?? '0');

                /*
                 * Accepted + Rejected must equal Received
                 */
                if (
                    bccomp(
                        bcadd($accepted, $rejected, 3),
                        $received,
                        3
                    ) !== 0
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'يجب أن يساوي مجموع الكمية المقبولة والمرفوضة الكمية المستلمة.'
                    ]);
                }

                /*
                 * Accepted quantity cannot exceed remaining PO quantity.
                 */
                if (bccomp($accepted, $remaining, 3) > 0) {
                    throw ValidationException::withMessages([
                        'items' =>
                            "لا يمكن أن تتجاوز الكمية المقبولة للخامة {$poItem->material->name} الكمية المتبقية وقدرها {$remaining}."
                    ]);
                }

                /*
                 * A supplier may send more than the remaining quantity,
                 * but only the accepted amount can fulfill the PO.
                 *
                 * Usually we keep it strict here:
                 * Received quantity cannot exceed remaining + rejected.
                 */
                $maxReceived = bcadd(
                    $remaining,
                    $rejected,
                    3
                );

                if (bccomp($received, $maxReceived, 3) > 0) {
                    throw ValidationException::withMessages([
                        'items' =>
                            "الكمية المستلمة للخامة {$poItem->material->name} تتجاوز الكمية المتبقية."
                    ]);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'material_id' => $poItem->material_id,
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
