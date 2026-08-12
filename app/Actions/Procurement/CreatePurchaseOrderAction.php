<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseOrder;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function __construct(private CodeGeneratorService $codes) {}
    public function handle(array $data): PurchaseOrder
{
    return DB::transaction(function () use ($data) {
        $items = $data['items'];
        unset($data['items']);

        $purchaseOrderNumber = $this->codes->next('PO');

        $po = PurchaseOrder::create([
            ...$data,
            'purchase_order_number' => $purchaseOrderNumber,
            'status' => 'draft',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $subtotal = '0';

        foreach ($items as $item) {
            $quantity = (string) $item['quantity'];
            $unitPrice = (string) $item['unit_price'];
            $discount = (string) ($item['discount'] ?? '0');
            $tax = (string) ($item['tax'] ?? '0');

            $lineSubtotal = bcmul($quantity, $unitPrice, 2);
            $lineTotal = bcadd(
                bcsub($lineSubtotal, $discount, 2),
                $tax,
                2
            );

            $po->items()->create([
                ...$item,
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => $lineTotal,
            ]);

            $subtotal = bcadd($subtotal, $lineTotal, 2);
        }

        $total = bcadd(
            bcsub($subtotal, (string) $po->discount, 2),
            (string) $po->tax,
            2
        );

        $po->update([
            'subtotal' => $subtotal,
            'total' => $total,
        ]);

        return $po->fresh('items');
    });
}
}
