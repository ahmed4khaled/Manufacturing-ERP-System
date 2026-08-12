<?php

use App\Actions\Procurement\CreatePurchaseReturnAction;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\CodeGeneratorService;
use Illuminate\Validation\ValidationException;

it('generates unique document numbers across repeated calls', function () {
    $codes = app(CodeGeneratorService::class);
    $numbers = [];

    foreach (range(1, 25) as $i) {
        $numbers[] = $codes->next('TEST');
    }

    expect($numbers)->toHaveCount(25)
        ->and(array_unique($numbers))->toHaveCount(25);
});

it('blocks purchase returns above accepted receipt quantity', function () {
    $user = \App\Models\User::factory()->create();

    $supplier = Supplier::create([
        'name' => 'Alpha Supplier',
        'code' => 'SUP-001',
        'status' => 'active',
    ]);

    $category = \App\Models\Category::create([
        'code' => 'CAT-001',
        'name' => 'Procurement',
        'status' => 'active',
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Test Product',
        'code' => 'PRD-01',
        'cost_price' => 10,
        'status' => 'active',
    ]);

    $color = \App\Models\Color::create([
        'name' => 'Black',
        'code' => 'BLK',
        'status' => 'active',
    ]);
    $size = \App\Models\Size::create([
        'name' => 'M',
        'code' => 'M',
        'status' => 'active',
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-001',
        'barcode' => 'BAR-001',
        'default_price' => 12.50,
        'quantity' => 0,
        'status' => 'active',
    ]);

    $po = PurchaseOrder::create([
        'purchase_order_number' => 'PO-TEST-1',
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'subtotal' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 0,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_variant_id' => $variant->id,
        'quantity' => 10,
        'unit_price' => 12.50,
        'discount' => 0,
        'tax' => 0,
        'line_total' => 125.00,
    ]);

    $receipt = GoodsReceipt::create([
        'receipt_number' => 'GR-TEST-1',
        'purchase_order_id' => $po->id,
        'supplier_id' => $supplier->id,
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'confirmed',
    ]);

    $receipt->items()->create([
        'purchase_order_item_id' => $poItem->id,
        'product_variant_id' => $variant->id,
        'ordered_quantity' => 10,
        'received_quantity' => 10,
        'accepted_quantity' => 8,
        'rejected_quantity' => 2,
        'unit_cost' => 12.50,
    ]);

    $action = app(CreatePurchaseReturnAction::class);

    expect(fn () => $action->handle([
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $po->id,
        'goods_receipt_id' => $receipt->id,
        'return_date' => now()->toDateString(),
        'reason' => 'Rejected items',
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 9,
                'unit_cost' => 12.50,
                'reason' => 'Too much',
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

it('confirms a purchase return and reduces stock once', function () {
    $user = \App\Models\User::factory()->create();

    $supplier = Supplier::create([
        'name' => 'Beta Supplier',
        'code' => 'SUP-002',
        'status' => 'active',
    ]);

    $category = \App\Models\Category::create([
        'code' => 'CAT-002',
        'name' => 'Returns',
        'status' => 'active',
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Return Product',
        'code' => 'PRD-02',
        'cost_price' => 9,
        'status' => 'active',
    ]);

    $color = \App\Models\Color::create([
        'name' => 'White',
        'code' => 'WHT',
        'status' => 'active',
    ]);
    $size = \App\Models\Size::create([
        'name' => 'L',
        'code' => 'L',
        'status' => 'active',
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-002',
        'barcode' => 'BAR-002',
        'default_price' => 15.00,
        'quantity' => 12,
        'status' => 'active',
    ]);

    $po = PurchaseOrder::create([
        'purchase_order_number' => 'PO-TEST-2',
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'subtotal' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 0,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_variant_id' => $variant->id,
        'quantity' => 12,
        'unit_price' => 15.00,
        'discount' => 0,
        'tax' => 0,
        'line_total' => 180.00,
    ]);

    $receipt = GoodsReceipt::create([
        'receipt_number' => 'GR-TEST-2',
        'purchase_order_id' => $po->id,
        'supplier_id' => $supplier->id,
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'confirmed',
    ]);

    $receipt->items()->create([
        'purchase_order_item_id' => $poItem->id,
        'product_variant_id' => $variant->id,
        'ordered_quantity' => 12,
        'received_quantity' => 12,
        'accepted_quantity' => 12,
        'rejected_quantity' => 0,
        'unit_cost' => 15.00,
    ]);

    $action = app(CreatePurchaseReturnAction::class);
    $returned = $action->handle([
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $po->id,
        'goods_receipt_id' => $receipt->id,
        'return_date' => now()->toDateString(),
        'reason' => 'Damaged shipment',
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
                'unit_cost' => 15.00,
                'reason' => 'Damaged',
            ],
        ],
    ]);

    expect($returned->status)->toBe('draft');

    $confirm = app(\App\Actions\Procurement\ConfirmPurchaseReturnAction::class);
    $confirmed = $confirm->handle($returned);
    expect($confirmed->status)->toBe('confirmed')
        ->and((string) $variant->fresh()->quantity)->toBe('9.000');
});
