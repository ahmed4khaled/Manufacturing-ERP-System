<?php

namespace App\Actions\Procurement;

use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\MaterialStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPurchaseReturnAction
{
    public function __construct(private MaterialStockService $materialStock) {}

    public function handle(PurchaseReturn $return): PurchaseReturn
    {
        return DB::transaction(function () use ($return) {
            $return = PurchaseReturn::query()->lockForUpdate()->findOrFail($return->id);
            if ($return->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'لا يمكن تأكيد إلا المرتجعات المسودة.']);
            }

            if (MaterialStockMovement::query()
                ->where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $return->id)
                ->exists()) {
                throw ValidationException::withMessages(['status' => 'تم ترحيل المرتجع إلى المخزون بالفعل.']);
            }

            $return->load(['items', 'goodsReceipt.items']);

            $materialIds = $return->items->pluck('material_id')->unique()->values()->all();
            Material::query()->whereIn('id', $materialIds)->lockForUpdate()->get();

            $existingReturnedByMaterial = PurchaseReturnItem::query()
                ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                ->where('purchase_returns.goods_receipt_id', $return->goods_receipt_id)
                ->where('purchase_returns.id', '!=', $return->id)
                ->where('purchase_returns.status', 'confirmed')
                ->groupBy('purchase_return_items.material_id')
                ->selectRaw('purchase_return_items.material_id, SUM(purchase_return_items.quantity) as returned_quantity')
                ->pluck('returned_quantity', 'material_id')
                ->all();

            foreach ($return->items as $item) {
                $receiptItem = $return->goodsReceipt?->items
                    ->first(fn ($receiptItem) => (int) $receiptItem->material_id === (int) $item->material_id);

                if (! $receiptItem) {
                    throw ValidationException::withMessages([
                        'items' => 'بند واحد أو أكثر من المرتجع لا يتبع إذن الاستلام المرجعي.',
                    ]);
                }

                $alreadyReturned = (string) ($existingReturnedByMaterial[$item->material_id] ?? '0');
                $remainingFromReceipt = bcsub((string) $receiptItem->accepted_quantity, $alreadyReturned, 3);

                if (bccomp($remainingFromReceipt, (string) $item->quantity, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'كمية المرتجع تتجاوز الكمية المقبولة والمتاحة من إذن الاستلام.',
                    ]);
                }

                $material = Material::query()->findOrFail($item->material_id);
                $available = $this->materialStock->getCurrentStock($material);

                if (bccomp($available, (string) $item->quantity, 3) < 0) {
                    throw ValidationException::withMessages(['items' => 'كمية المرتجع تتجاوز الرصيد المتاح من الخامة.']);
                }
            }

            $return->update(['status' => 'confirmed']);
            $this->materialStock->createPurchaseReturnMovements($return);

            return $return->fresh('items');
        });
    }
}
