<?php

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreatePurchaseReturnAction;
use App\Enums\MaterialStockMovementType;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CodeGeneratorService;
use App\Services\MaterialStockService;
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

/** @return array{supplier: Supplier, material: Material, order: PurchaseOrder, receipt: GoodsReceipt} */
function procurementReturnContext(
    string $orderedQuantity,
    string $acceptedQuantity,
    string $rejectedQuantity,
): array {
    $user = User::factory()->create();
    test()->actingAs($user);

    $supplier = Supplier::create([
        'name' => 'مورد اختبار المرتجعات',
        'code' => 'SUP-RETURN',
        'status' => 'active',
    ]);
    $material = Material::create([
        'code' => 'MAT-RETURN',
        'name' => 'قماش اختبار المرتجعات',
        'category' => 'fabric',
        'unit' => 'meter',
        'minimum_stock' => 0,
        'quantity' => 0,
        'status' => 'active',
    ]);
    $order = PurchaseOrder::create([
        'purchase_order_number' => 'PO-RETURN',
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'subtotal' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 0,
    ]);
    $orderItem = PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'material_id' => $material->id,
        'quantity' => $orderedQuantity,
        'unit_price' => '12.50',
        'discount' => 0,
        'tax' => 0,
        'line_total' => bcmul($orderedQuantity, '12.50', 2),
    ]);
    $receipt = GoodsReceipt::create([
        'receipt_number' => 'GR-RETURN',
        'purchase_order_id' => $order->id,
        'supplier_id' => $supplier->id,
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'draft',
    ]);
    $receipt->items()->create([
        'purchase_order_item_id' => $orderItem->id,
        'material_id' => $material->id,
        'ordered_quantity' => $orderedQuantity,
        'received_quantity' => bcadd($acceptedQuantity, $rejectedQuantity, 3),
        'accepted_quantity' => $acceptedQuantity,
        'rejected_quantity' => $rejectedQuantity,
        'unit_cost' => '12.50',
    ]);

    app(ConfirmGoodsReceiptAction::class)->handle($receipt);

    return compact('supplier', 'material', 'order', 'receipt');
}

function procurementReturnData(array $context, string $quantity): array
{
    return [
        'supplier_id' => $context['supplier']->id,
        'purchase_order_id' => $context['order']->id,
        'goods_receipt_id' => $context['receipt']->id,
        'return_date' => now()->toDateString(),
        'reason' => 'خامات غير مطابقة',
        'items' => [[
            'material_id' => $context['material']->id,
            'quantity' => $quantity,
            'unit_cost' => '12.50',
            'reason' => 'عيب في الخامة',
        ]],
    ];
}

it('blocks purchase returns above accepted material receipt quantity', function () {
    $context = procurementReturnContext('10.000', '8.000', '2.000');

    expect(app(MaterialStockService::class)->getCurrentStock($context['material']))
        ->toBe('8.000');

    expect(fn () => app(CreatePurchaseReturnAction::class)
        ->handle(procurementReturnData($context, '8.001')))
        ->toThrow(ValidationException::class);
});

it('confirms a material purchase return once and rejects invalid repeated quantities', function () {
    $context = procurementReturnContext('12.000', '12.000', '0.000');
    $returned = app(CreatePurchaseReturnAction::class)
        ->handle(procurementReturnData($context, '3.000'));

    $confirm = app(ConfirmPurchaseReturnAction::class);
    $confirmed = $confirm->handle($returned);

    expect($confirmed->status)->toBe('confirmed')
        ->and(app(MaterialStockService::class)->getCurrentStock($context['material']))->toBe('9.000')
        ->and((string) $context['material']->fresh()->quantity)->toBe('9.000')
        ->and(MaterialStockMovement::query()
            ->where('movement_type', MaterialStockMovementType::PURCHASE_RETURN)
            ->where('reference_id', $returned->id)
            ->count())->toBe(1);

    expect(fn () => $confirm->handle($returned))
        ->toThrow(ValidationException::class);

    expect(fn () => app(CreatePurchaseReturnAction::class)
        ->handle(procurementReturnData($context, '9.001')))
        ->toThrow(ValidationException::class);

    expect(app(MaterialStockService::class)->getCurrentStock($context['material']))
        ->toBe('9.000');
});
