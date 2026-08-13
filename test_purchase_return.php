<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Supplier, Material, PurchaseOrder, PurchaseOrderItem, GoodsReceipt, GoodsReceiptItem, PurchaseReturn, PurchaseReturnItem, MaterialStockMovement};
use App\Actions\Procurement\{CreatePurchaseOrderAction, ApprovePurchaseOrderAction, CreateGoodsReceiptAction, ConfirmGoodsReceiptAction, CreatePurchaseReturnAction, ConfirmPurchaseReturnAction};
use App\Services\{CodeGeneratorService, MaterialStockService};

echo "=== PURCHASE RETURN WORKFLOW TEST ===" . PHP_EOL . PHP_EOL;

$timestamp = time();

// 1. Create supplier
$supplier = Supplier::create([
    'name' => "Test Supplier {$timestamp}",
    'code' => "SUPP-TEST-{$timestamp}",
    'status' => 'active',
]);
echo "✓ Created supplier: {$supplier->name}" . PHP_EOL;

// 2. Create material
$material = Material::create([
    'code' => "MAT-TEST-{$timestamp}",
    'name' => "Test Material {$timestamp}",
    'category' => 'fabric',
    'unit' => 'meter',
    'status' => 'active',
    'quantity' => '0',
]);
echo "✓ Created material: {$material->name}" . PHP_EOL;

// 3. Create and approve PO
$po = PurchaseOrder::create([
    'supplier_id' => $supplier->id,
    'purchase_order_number' => "PO-TEST-{$timestamp}",
    'order_date' => now(),
    'expected_delivery_date' => now()->addDays(7),
    'status' => 'draft',
    'subtotal' => '10000',
    'discount' => '0',
    'tax' => '1000',
    'total' => '11000',
]);

PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'material_id' => $material->id,
    'quantity' => '100',
    'unit_price' => '100',
    'discount' => '0',
    'tax' => '0',
    'line_total' => '10000',
]);

echo "✓ Created PO: {$po->purchase_order_number}" . PHP_EOL;

// Approve PO
$approveAction = new ApprovePurchaseOrderAction();
$po = $approveAction->handle($po);
echo "✓ Approved PO, status: {$po->status}" . PHP_EOL;

// 4. Create goods receipt
$grAction = new CreateGoodsReceiptAction(app(CodeGeneratorService::class));
$gr = $grAction->handle([
    'purchase_order_id' => $po->id,
    'received_at' => now(),
    'items' => [
        [
            'purchase_order_item_id' => $po->items->first()->id,
            'received_quantity' => '100',
            'accepted_quantity' => '95',
            'rejected_quantity' => '5',
            'unit_cost' => '100',
        ],
    ],
    'notes' => 'Test receipt',
]);
echo "✓ Created Goods Receipt: {$gr->receipt_number}, status: {$gr->status}" . PHP_EOL;

// 5. Confirm goods receipt
$confirmGRAction = new ConfirmGoodsReceiptAction(app(MaterialStockService::class));
$gr = $confirmGRAction->handle($gr);
echo "✓ Confirmed Goods Receipt, status: {$gr->status}" . PHP_EOL;

// Check material stock after GR confirmation
$stockService = app(MaterialStockService::class);
$stock = $stockService->getCurrentStock($material);
echo "  Material stock after GR confirmation: {$stock}" . PHP_EOL;

// 6. Create purchase return
$createReturnAction = new CreatePurchaseReturnAction(app(CodeGeneratorService::class));
$return = $createReturnAction->handle([
    'goods_receipt_id' => $gr->id,
    'supplier_id' => $supplier->id,
    'return_date' => now()->toDateString(),
    'reason' => 'Test return',
    'items' => [
        [
            'material_id' => $material->id,
            'quantity' => '10',
            'unit_cost' => '100',
        ],
    ],
]);
echo "✓ Created Purchase Return: {$return->return_number}, status: {$return->status}" . PHP_EOL;

// 7. Confirm purchase return
$confirmReturnAction = new ConfirmPurchaseReturnAction(app(MaterialStockService::class));
$return = $confirmReturnAction->handle($return);
echo "✓ Confirmed Purchase Return, status: {$return->status}" . PHP_EOL;

// Check material stock after return confirmation
$stock = $stockService->getCurrentStock($material);
echo "  Material stock after return confirmation: {$stock}" . PHP_EOL;

// 8. Verify movements
$movements = MaterialStockMovement::where('material_id', $material->id)->orderBy('id')->get();
echo PHP_EOL . "Material Stock Movements:" . PHP_EOL;
foreach ($movements as $m) {
    echo "  - {$m->movement_type->value} / {$m->direction->value}: {$m->quantity}" . PHP_EOL;
}

echo PHP_EOL . "=== TEST COMPLETE ===" . PHP_EOL;
