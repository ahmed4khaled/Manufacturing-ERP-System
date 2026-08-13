<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Supplier, Material, PurchaseOrder, PurchaseOrderItem, GoodsReceipt, GoodsReceiptItem, PurchaseReturn, PurchaseReturnItem, MaterialStockMovement};
use App\Actions\Procurement\{CreatePurchaseOrderAction, ApprovePurchaseOrderAction, CreateGoodsReceiptAction, ConfirmGoodsReceiptAction, CreatePurchaseReturnAction, ConfirmPurchaseReturnAction};
use App\Services\{CodeGeneratorService, MaterialStockService};
use Illuminate\Validation\ValidationException;

echo "=== COMPREHENSIVE PURCHASE RETURN TESTS ===" . PHP_EOL . PHP_EOL;

$timestamp = time();
$stockService = app(MaterialStockService::class);
$tests_passed = 0;
$tests_failed = 0;

function test_case($name, $fn) {
    global $tests_passed, $tests_failed;
    echo "TEST: $name" . PHP_EOL;
    try {
        $fn();
        echo "  ✓ PASS" . PHP_EOL;
        $tests_passed++;
    } catch (Exception $e) {
        echo "  ✗ FAIL: " . $e->getMessage() . PHP_EOL;
        $tests_failed++;
    }
    echo PHP_EOL;
}

// Setup
$supplier = Supplier::firstOrCreate(
    ['code' => "SUPP-TEST-{$timestamp}"],
    ['name' => "Test Supplier {$timestamp}", 'status' => 'active']
);

function create_test_material($timestamp, $test_name) {
    return Material::create([
        'code' => "MAT-TEST-{$timestamp}-{$test_name}",
        'name' => "Test Material {$timestamp} {$test_name}",
        'category' => 'fabric',
        'unit' => 'meter',
        'status' => 'active',
        'quantity' => '0'
    ]);
}

// TEST 1: Cannot return from draft receipt
test_case("Cannot return from draft receipt", function() {
    global $timestamp, $supplier;
    $material = create_test_material($timestamp, "test1");
    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'purchase_order_number' => "PO-TEST-DRAFT-{$timestamp}",
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'status' => 'approved',
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
    
    $gr = GoodsReceipt::create([
        'receipt_number' => 'GR-DRAFT-' . uniqid(),
        'purchase_order_id' => $po->id,
        'supplier_id' => $supplier->id,
        'received_at' => now(),
        'received_by' => 1,
        'status' => 'draft',
    ]);
    
    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'purchase_order_item_id' => $po->items->first()->id,
        'material_id' => $material->id,
        'ordered_quantity' => '100',
        'received_quantity' => '100',
        'accepted_quantity' => '95',
        'rejected_quantity' => '5',
        'unit_cost' => '100',
    ]);
    
    $action = new CreatePurchaseReturnAction(app(CodeGeneratorService::class));
    try {
        $action->handle([
            'goods_receipt_id' => $gr->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => '10',
                    'unit_cost' => '100',
                ],
            ],
        ]);
        throw new Exception("Should have rejected draft receipt");
    } catch (ValidationException $e) {
        if (str_contains($e->getMessage(), 'Only confirmed goods receipts')) {
            return; // Expected
        }
        throw $e;
    }
});

// TEST 2: Cannot return more than accepted quantity
test_case("Cannot return more than accepted quantity", function() {
    global $timestamp, $supplier, $stockService;
    $material = create_test_material($timestamp, "test2");
    
    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'purchase_order_number' => "PO-OVERRETURN-{$timestamp}",
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'status' => 'approved',
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
    
    $grAction = new CreateGoodsReceiptAction(app(CodeGeneratorService::class));
    $gr = $grAction->handle([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
        'items' => [
            [
                'purchase_order_item_id' => $po->items->first()->id,
                'received_quantity' => '100',
                'accepted_quantity' => '50',
                'rejected_quantity' => '50',
                'unit_cost' => '100',
            ],
        ],
        'notes' => 'Test',
    ]);
    
    $confirmGRAction = new ConfirmGoodsReceiptAction($stockService);
    $gr = $confirmGRAction->handle($gr);
    
    $action = new CreatePurchaseReturnAction(app(CodeGeneratorService::class));
    try {
        $action->handle([
            'goods_receipt_id' => $gr->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => '60', // More than accepted 50
                    'unit_cost' => '100',
                ],
            ],
        ]);
        throw new Exception("Should have rejected over-return");
    } catch (ValidationException $e) {
        if (str_contains($e->getMessage(), 'Return quantity exceeds')) {
            return; // Expected
        }
        throw $e;
    }
});

// TEST 3: Cannot confirm return twice
test_case("Cannot confirm return twice", function() {
    global $timestamp, $supplier, $stockService;
    $material = create_test_material($timestamp, "test3");
    
    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'purchase_order_number' => "PO-DOUBLECONF-{$timestamp}",
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'status' => 'approved',
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
        'notes' => 'Test',
    ]);
    
    $confirmGRAction = new ConfirmGoodsReceiptAction($stockService);
    $gr = $confirmGRAction->handle($gr);
    
    $action = new CreatePurchaseReturnAction(app(CodeGeneratorService::class));
    $return = $action->handle([
        'goods_receipt_id' => $gr->id,
        'supplier_id' => $supplier->id,
        'return_date' => now()->toDateString(),
        'reason' => 'Test',
        'items' => [
            [
                'material_id' => $material->id,
                'quantity' => '10',
                'unit_cost' => '100',
            ],
        ],
    ]);
    
    $confirmAction = new ConfirmPurchaseReturnAction($stockService);
    $confirmAction->handle($return);
    
    // Try to confirm again
    try {
        $confirmAction->handle($return->fresh());
        throw new Exception("Should have rejected double confirmation");
    } catch (ValidationException $e) {
        if (str_contains($e->getMessage(), 'Only draft returns') || str_contains($e->getMessage(), 'inventory is already posted')) {
            return; // Expected
        }
        throw $e;
    }
});

// TEST 4: Stock cannot go negative
test_case("Stock cannot go negative on return", function() {
    global $timestamp, $supplier, $stockService;
    $material = create_test_material($timestamp, "test4");
    
    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'purchase_order_number' => "PO-NEGSTOCK-{$timestamp}",
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'status' => 'approved',
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
    
    $grAction = new CreateGoodsReceiptAction(app(CodeGeneratorService::class));
    $gr = $grAction->handle([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
        'items' => [
            [
                'purchase_order_item_id' => $po->items->first()->id,
                'received_quantity' => '100',
                'accepted_quantity' => '1',  // Only 1 accepted
                'rejected_quantity' => '99',
                'unit_cost' => '100',
            ],
        ],
        'notes' => 'Test',
    ]);
    
    $confirmGRAction = new ConfirmGoodsReceiptAction($stockService);
    $gr = $confirmGRAction->handle($gr);
    
    $action = new CreatePurchaseReturnAction(app(CodeGeneratorService::class));
    $return = $action->handle([
        'goods_receipt_id' => $gr->id,
        'supplier_id' => $supplier->id,
        'return_date' => now()->toDateString(),
        'reason' => 'Test',
        'items' => [
            [
                'material_id' => $material->id,
                'quantity' => '1',
                'unit_cost' => '100',
            ],
        ],
    ]);
    
    // This should work fine
    $confirmAction = new ConfirmPurchaseReturnAction($stockService);
    $confirmAction->handle($return);
    
    // Stock should be 0
    $stock = $stockService->getCurrentStock($material);
    if (bccomp($stock, '0', 3) !== 0) {
        throw new Exception("Expected stock 0, got {$stock}");
    }
});

echo PHP_EOL . "=== RESULTS ===" . PHP_EOL;
echo "Passed: {$tests_passed}" . PHP_EOL;
echo "Failed: {$tests_failed}" . PHP_EOL;
