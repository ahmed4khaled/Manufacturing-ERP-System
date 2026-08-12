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
    public function __construct(private readonly CodeGeneratorService $codeGenerator)
    {
    }

    public function handle(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $receipt = GoodsReceipt::query()
                ->with(['items.productVariant', 'purchaseOrder.items'])
                ->lockForUpdate()
                ->findOrFail($data['goods_receipt_id']);

            if ($receipt->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'Only confirmed goods receipts can be returned.',
                ]);
            }

            if ((int) $receipt->supplier_id !== (int) ($data['supplier_id'] ?? $receipt->supplier_id)) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'The supplier on the return must match the goods receipt supplier.',
                ]);
            }

            if (! empty($data['purchase_order_id']) && (int) $receipt->purchase_order_id !== (int) $data['purchase_order_id']) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'The purchase order on the return must match the goods receipt.',
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

            $existingReturnedByVariant = PurchaseReturnItem::query()
                ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                ->where('purchase_returns.goods_receipt_id', $receipt->id)
                ->groupBy('purchase_return_items.product_variant_id')
                ->selectRaw('purchase_return_items.product_variant_id, SUM(purchase_return_items.quantity) as returned_quantity')
                ->lockForUpdate()
                ->pluck('returned_quantity', 'product_variant_id')
                ->all();

            foreach ($data['items'] as $line) {
                $variantId = (int) $line['product_variant_id'];
                $quantity = (string) ($line['quantity'] ?? 0);

                $receiptItem = $receipt->items->first(fn (GoodsReceiptItem $item) => (int) $item->product_variant_id === $variantId);
                if (! $receiptItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more return items do not belong to the selected goods receipt.',
                    ]);
                }

                $availableAccepted = (string) $receiptItem->accepted_quantity;
                $alreadyReturned = (string) ($existingReturnedByVariant[$variantId] ?? '0');
                $remaining = bcsub($availableAccepted, $alreadyReturned, 3);

                if (bccomp($remaining, (string) $quantity, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity exceeds the accepted quantity available from this receipt.',
                    ]);
                }

                $return->items()->create([
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_cost' => $line['unit_cost'] ?? $receiptItem->unit_cost,
                    'reason' => $line['reason'] ?? null,
                ]);
                $existingReturnedByVariant[$variantId] = bcadd($existingReturnedByVariant[$variantId] ?? '0', $quantity, 3);
            }

            return $return->fresh('items.productVariant');
        });
    }
}
