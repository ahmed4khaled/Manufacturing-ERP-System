<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\MaterialStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmGoodsReceiptAction
{
    public function __construct(private MaterialStockService $materialStock) {}

    public function handle(GoodsReceipt $receipt): GoodsReceipt
    {
        
        return DB::transaction(function () use ($receipt) {
            $receipt = GoodsReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد إلا السندات المسودة.',
                ]);
            }

            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->find($receipt->purchase_order_id);

            $receipt->load('items.purchaseOrderItem');

            $poItemIds = $receipt->items
                ->pluck('purchase_order_item_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
PurchaseOrderItem::query()
    ->whereIn('id', $poItemIds)
    ->lockForUpdate()
    ->get();

            if (!empty($poItemIds)) {
                PurchaseOrderItem::query()
                    ->whereIn('id', $poItemIds)
                    ->lockForUpdate()
                    ->get();
            }

            if (
                MaterialStockMovement::query()
                    ->where('reference_type', GoodsReceipt::class)
                    ->where('reference_id', $receipt->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'تم ترحيل إذن الاستلام إلى المخزون بالفعل.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Receipt Items
            |--------------------------------------------------------------------------
            */

            foreach ($receipt->items as $item) {

                if (
                    !$item->purchaseOrderItem ||
                    $item->purchaseOrderItem->purchase_order_id !== $receipt->purchase_order_id
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'يجب أن تتبع بنود الاستلام أمر الشراء المرجعي.',
                    ]);
                }

                if (
                    (int) $item->material_id !==
                    (int) $item->purchaseOrderItem->material_id
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'يجب أن تطابق خامة بند الاستلام خامة بند أمر الشراء.',
                    ]);
                }

               $acceptedPreviously = (string) $item->purchaseOrderItem->receiptItems()
    ->whereHas('receipt', fn ($q) => $q->where('status', 'confirmed'))
    ->sum('accepted_quantity');

$newAccepted = bcadd(
    $acceptedPreviously,
    (string) $item->accepted_quantity,
    3
);

$ordered = (string) $item->ordered_quantity;

if (bccomp($newAccepted, $ordered, 3) > 0) {
    throw ValidationException::withMessages([
        'items' => 'الكمية المقبولة تتجاوز الكمية المتبقية في أمر الشراء.'
    ]);
}

               
                $acceptedPlusRejected = bcadd(
                    (string) $item->accepted_quantity,
                    (string) $item->rejected_quantity,
                    3
                );

                if (
                    bccomp(
                        $acceptedPlusRejected,
                        (string) $item->received_quantity,
                        3
                    ) !== 0
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'يجب أن يساوي مجموع الكمية المقبولة والمرفوضة الكمية المستلمة.',
                    ]);
                }
            }
            /*
            |--------------------------------------------------------------------------
            | Confirm Receipt
            |--------------------------------------------------------------------------
            */

            $receipt->update([
                'status' => 'confirmed',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Add ONLY accepted quantity to inventory
            |--------------------------------------------------------------------------
            */

            $this->materialStock->createPurchaseReceiptMovements($receipt);

            /*
            |--------------------------------------------------------------------------
            | Update Purchase Order Status
            |--------------------------------------------------------------------------
            */

            if ($po) {

              $all = $po->items()->get()->every(function ($i) {

    $accepted = (string) $i->receiptItems()
        ->whereHas(
            'receipt',
            fn ($q) => $q->where('status', 'confirmed')
        )
        ->sum('accepted_quantity');

    return bccomp(
        $accepted,
        (string) $i->quantity,
        3
    ) >= 0;
});

               $po->update([
    'status' => $all
        ? 'fully_received'
        : 'partially_received'
]);
            }

            return $receipt->fresh('items');
        });
    }
}