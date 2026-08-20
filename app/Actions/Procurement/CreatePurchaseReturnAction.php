<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseReturnAction
{
    public function __construct(private readonly CodeGeneratorService $codeGenerator) {}

    public function handle(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $receipt = GoodsReceipt::query()
                ->with(['items.material', 'purchaseOrder.items'])
                ->lockForUpdate()
                ->findOrFail($data['goods_receipt_id']);

            if ($receipt->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'لا يمكن إنشاء مرتجع إلا من إذن استلام مؤكد.',
                ]);
            }

            if ((int) $receipt->supplier_id !== (int) ($data['supplier_id'] ?? $receipt->supplier_id)) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'يجب أن يطابق مورد المرتجع مورد إذن الاستلام.',
                ]);
            }

            if (! empty($data['purchase_order_id']) && (int) $receipt->purchase_order_id !== (int) $data['purchase_order_id']) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'يجب أن يطابق أمر الشراء في المرتجع أمر إذن الاستلام.',
                ]);
            }

            $return = PurchaseReturn::create([
                'return_number' => $this->codeGenerator->next('RTN'),
                'supplier_id' => $receipt->supplier_id,
                'purchase_order_id' => $receipt->purchase_order_id,
                'goods_receipt_id' => $receipt->id,
                'return_date' => $data['return_date'],
                'reason' => $data['reason'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $existingReturnedByMaterial = PurchaseReturnItem::query()
                ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                ->where('purchase_returns.goods_receipt_id', $receipt->id)
                ->whereIn('purchase_returns.status', ['draft', 'confirmed'])
                ->groupBy('purchase_return_items.material_id')
                ->selectRaw('purchase_return_items.material_id, SUM(purchase_return_items.quantity) as returned_quantity')
                ->pluck('returned_quantity', 'material_id')
                ->all();

            foreach ($data['items'] as $line) {
                $materialId = (int) $line['material_id'];
                $quantity = (string) ($line['quantity'] ?? '0');

                $receiptItem = $receipt->items->first(fn (GoodsReceiptItem $item) => (int) $item->material_id === $materialId);
                if (! $receiptItem) {
                    throw ValidationException::withMessages([
                        'items' => 'بند واحد أو أكثر من المرتجع لا يتبع إذن الاستلام المحدد.',
                    ]);
                }

                $availableAccepted = (string) $receiptItem->accepted_quantity;
                $alreadyReturned = (string) ($existingReturnedByMaterial[$materialId] ?? '0');
                $remaining = bcsub($availableAccepted, $alreadyReturned, 3);

                if (bccomp($remaining, $quantity, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'كمية المرتجع تتجاوز الكمية المقبولة والمتاحة من إذن الاستلام.',
                    ]);
                }

                $return->items()->create([
                    'material_id' => $materialId,
                    'quantity' => $quantity,
                    'unit_cost' => $line['unit_cost'] ?? $receiptItem->unit_cost,
                    'reason' => $line['reason'] ?? null,
                ]);
                $existingReturnedByMaterial[$materialId] = bcadd($existingReturnedByMaterial[$materialId] ?? '0', $quantity, 3);
            }

            return $return->fresh('items.material');
        });
    }
}
