<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialReturnAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesReturn\CancelSalesReturnAction;
use App\Actions\SalesReturn\ConfirmSalesReturnAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Enums\SalesInvoiceStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialCostAllocation;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryCostingService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function costingMaterial(): Material
{
    return Material::create(['code' => 'FIFO-MAT', 'name' => 'FIFO Material', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 200, 'status' => 'active']);
}

function costingMaterialLayer(Material $material, string $quantity, string $unitCost, string $date): MaterialCostLayer
{
    $movement = MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'purchase_receipt', 'direction' => 'in', 'quantity' => $quantity, 'movement_date' => $date]);

    return MaterialCostLayer::create(['material_id' => $material->id, 'material_stock_movement_id' => $movement->id, 'layer_date' => $date, 'original_quantity' => $quantity, 'remaining_quantity' => $quantity, 'unit_cost' => $unitCost, 'total_original_cost' => bcmul($quantity, $unitCost, 2)]);
}

/** @return array{variant: ProductVariant, customer: Customer} */
function costingSalesFixture(): array
{
    $category = Category::create(['code' => 'FIFO-CAT', 'name' => 'FIFO Category', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => 'FIFO Abaya', 'code' => 'FIFO-P', 'base_cost_price' => 0, 'cost_price' => 0, 'status' => 'active']);
    $color = Color::create(['name' => 'Black FIFO', 'code' => 'FBLK', 'status' => 'active']);
    $size = Size::create(['name' => 'M FIFO', 'code' => 'FM', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'FIFO-SKU', 'barcode' => 'FIFO-BAR', 'default_price' => 500, 'quantity' => 200, 'status' => 'active']);
    $customer = Customer::create(['code' => 'FIFO-CUS', 'name' => 'FIFO Customer', 'phone' => '01000999000', 'credit_limit' => 1000000, 'status' => 'active']);

    return compact('variant', 'customer');
}

function costingProductLayer(ProductVariant $variant, string $quantity, string $unitCost, string $date): ProductCostLayer
{
    $movement = StockMovement::create(['product_variant_id' => $variant->id, 'movement_type' => StockMovementType::PRODUCTION_OUTPUT, 'direction' => StockMovementDirection::IN, 'quantity' => $quantity, 'movement_date' => $date]);

    return ProductCostLayer::create(['product_variant_id' => $variant->id, 'stock_movement_id' => $movement->id, 'layer_date' => $date, 'original_quantity' => $quantity, 'remaining_quantity' => $quantity, 'unit_cost' => $unitCost, 'total_original_cost' => bcmul($quantity, $unitCost, 2), 'is_final' => true]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('consumes material layers in exact FIFO order across multiple layers', function () {
    $material = costingMaterial();
    $first = costingMaterialLayer($material, '100.000', '10.0000', '2026-01-01');
    $second = costingMaterialLayer($material, '100.000', '12.0000', '2026-01-02');
    $movement = MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'adjustment_out', 'direction' => 'out', 'quantity' => '150.000', 'movement_date' => '2026-01-03']);

    $cost = app(InventoryCostingService::class)->consumeMaterialMovement($movement);
    $allocations = MaterialCostAllocation::query()->where('material_stock_movement_id', $movement->id)->orderBy('id')->get();

    expect($cost)->toBe('1600.00')
        ->and($allocations)->toHaveCount(2)
        ->and((string) $allocations[0]->quantity)->toBe('100.000')
        ->and((string) $allocations[0]->unit_cost)->toBe('10.0000')
        ->and((string) $allocations[1]->quantity)->toBe('50.000')
        ->and((string) $allocations[1]->unit_cost)->toBe('12.0000')
        ->and((string) $first->fresh()->remaining_quantity)->toBe('0.000')
        ->and((string) $second->fresh()->remaining_quantity)->toBe('50.000')
        ->and((string) $first->fresh()->unit_cost)->toBe('10.0000');
});

it('rolls back all FIFO layer changes when the surrounding transaction fails', function () {
    $material = costingMaterial();
    $layer = costingMaterialLayer($material, '100.000', '10.0000', '2026-01-01');

    try {
        DB::transaction(function () use ($material): void {
            $movement = MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'adjustment_out', 'direction' => 'out', 'quantity' => '30.000', 'movement_date' => '2026-01-02']);
            app(InventoryCostingService::class)->consumeMaterialMovement($movement);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect((string) $layer->fresh()->remaining_quantity)->toBe('100.000')
        ->and(MaterialCostAllocation::query()->count())->toBe(0);
});

it('calculates finished goods FIFO COGS and is idempotent on repeated confirmation', function () {
    ['variant' => $variant, 'customer' => $customer] = costingSalesFixture();
    $first = costingProductLayer($variant, '100.000', '300.0000', '2026-01-01');
    $second = costingProductLayer($variant, '100.000', '350.0000', '2026-01-02');
    $invoice = SalesInvoice::create(['invoice_number' => 'FIFO-INV', 'customer_id' => $customer->id, 'invoice_date' => '2026-01-03', 'status' => SalesInvoiceStatus::DRAFT, 'total_quantity' => 120, 'subtotal' => 60000, 'discount' => 0, 'total_amount' => 60000]);
    $invoice->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'sku' => $variant->sku, 'quantity' => 120, 'unit_price' => 500, 'line_total' => 60000]);

    $action = app(ConfirmSalesInvoiceAction::class);
    expect((string) $first->total_original_cost)->toBe('30000.00');
    $action->handle($invoice);
    $action->handle($invoice);

    expect((string) $invoice->fresh()->total_cogs)->toBe('37000.00')
        ->and((string) $first->fresh()->remaining_quantity)->toBe('0.000')
        ->and((string) $second->fresh()->remaining_quantity)->toBe('80.000')
        ->and(ProductCostAllocation::query()->where('reference_type', SalesInvoice::class)->where('reference_id', $invoice->id)->where('direction', 'out')->count())->toBe(2)
        ->and(StockMovement::query()->where('reference_type', SalesInvoice::class)->where('reference_id', $invoice->id)->where('movement_type', StockMovementType::SALE)->count())->toBe(1);
});

it('restores the exact historical finished goods costs when a sale is cancelled', function () {
    ['variant' => $variant, 'customer' => $customer] = costingSalesFixture();
    $first = costingProductLayer($variant, '100.000', '300.0000', '2026-01-01');
    $second = costingProductLayer($variant, '100.000', '350.0000', '2026-01-02');
    $invoice = SalesInvoice::create(['invoice_number' => 'FIFO-CANCEL', 'customer_id' => $customer->id, 'invoice_date' => '2026-01-03', 'status' => SalesInvoiceStatus::DRAFT, 'total_quantity' => 120, 'subtotal' => 60000, 'discount' => 0, 'total_amount' => 60000]);
    $invoice->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'sku' => $variant->sku, 'quantity' => 120, 'unit_price' => 500, 'line_total' => 60000]);
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);
    app(CancelSalesInvoiceAction::class)->handle($invoice);

    expect((string) $first->fresh()->remaining_quantity)->toBe('100.000')
        ->and((string) $second->fresh()->remaining_quantity)->toBe('100.000')
        ->and(ProductCostAllocation::query()->where('reference_type', SalesInvoice::class)->where('reference_id', $invoice->id)->where('direction', 'in')->count())->toBe(2);
});

it('calculates actual production cost from consumed material FIFO and assigns it only to the produced variant', function () {
    $category = Category::create(['code' => 'ACT-CAT', 'name' => 'Actual Cost', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Actual Abaya', 'code' => 'ACT-P', 'base_cost_price' => 100, 'cost_price' => 100, 'status' => 'active']);
    $color = Color::create(['name' => 'Actual Black', 'code' => 'ABLK', 'status' => 'active']);
    $sizeM = Size::create(['name' => 'Actual M', 'code' => 'AM', 'status' => 'active']);
    $sizeL = Size::create(['name' => 'Actual L', 'code' => 'AL', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $sizeM->id, 'sku' => 'ACT-M', 'barcode' => 'ACT-M-BAR', 'default_price' => 0, 'quantity' => 0, 'status' => 'active']);
    $otherVariant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $sizeL->id, 'sku' => 'ACT-L', 'barcode' => 'ACT-L-BAR', 'default_price' => 0, 'quantity' => 0, 'status' => 'active']);
    $material = Material::create(['code' => 'ACT-MAT', 'name' => 'Actual Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 30, 'status' => 'active']);
    costingMaterialLayer($material, '30.000', '20.0000', '2026-01-01');
    $operation = ManufacturingOperation::create(['name' => 'Actual Cutting', 'default_unit_cost' => 10, 'status' => 'active']);
    $worker = Worker::create(['code' => 'ACT-W', 'name' => 'Actual Worker', 'status' => 'active']);
    $bom = app(CreateBomAction::class)->handle(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'basis_quantity' => '1.000', 'items' => [['material_id' => $material->id, 'quantity' => '3.000']], 'operations' => [['manufacturing_operation_id' => $operation->id]]]);
    $bom = app(ActivateBomAction::class)->handle($bom);
    $order = app(CreateProductionOrderAction::class)->handle(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'bom_id' => $bom->id, 'planned_quantity' => '10.000', 'planned_start_date' => '2026-01-02', 'assignments' => [['bom_operation_id' => $bom->operations->first()->id, 'worker_id' => $worker->id, 'assigned_quantity' => '10.000', 'materials' => [$bom->items->first()->id => ['allocated_quantity' => '30.000']]]]]);
    app(ConfirmProductionOrderAction::class)->handle($order);
    $assignment = $order->workAssignments()->first();
    $allocation = $assignment->materials()->first();
    $event = ['quantity' => '30.000', 'occurred_at' => '2026-01-03'];
    $issue = app(IssueProductionMaterialsAction::class)->handle($allocation, $event);
    $return = app(RecordMaterialReturnAction::class)->handle($allocation, ['quantity' => '10.000', 'occurred_at' => '2026-01-03']);
    app(StartWorkAssignmentAction::class)->handle($assignment);
    app(RecordMaterialUsageAction::class)->handle($allocation, ['quantity' => '20.000', 'occurred_at' => '2026-01-03']);
    app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => '10.000', 'good_quantity' => '10.000', 'defective_quantity' => '0.000']);
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '10.000', 'output_date' => '2026-01-04']);
    app(CompleteProductionOrderAction::class)->handle($order);

    Permission::findOrCreate('production_orders.view', 'web');
    auth()->user()->givePermissionTo('production_orders.view');
    $costingPage = $this->get(route('production-orders.show', $order));
    $costingPage->assertOk()
        ->assertSee('تخصيصات FIFO الفعلية للخامات')
        ->assertSee('400.00')
        ->assertSee('500.00')
        ->assertSee('50.0000')
        ->assertSee('30.000')
        ->assertSee('20.0000');

    expect((string) $issue->fresh()->actual_cost)->toBe('600.00')
        ->and((string) $return->fresh()->actual_cost)->toBe('200.00')
        ->and((string) $order->fresh()->actual_material_cost)->toBe('400.00')
        ->and((string) $order->fresh()->actual_operation_cost)->toBe('100.00')
        ->and((string) $order->fresh()->final_total_cost)->toBe('500.00')
        ->and((string) $order->fresh()->final_unit_cost)->toBe('50.0000')
        ->and((string) $variant->fresh()->actual_cost)->toBe('50.0000')
        ->and($otherVariant->fresh()->actual_cost)->toBeNull()
        ->and((string) $product->fresh()->cost_price)->toBe('110.00')
        ->and((string) ProductCostLayer::query()->where('product_variant_id', $variant->id)->value('unit_cost'))->toBe('50.0000')
        ->and((string) MaterialCostLayer::query()->where('material_id', $material->id)->value('remaining_quantity'))->toBe('10.000');
});

it('creates receipt-priced material layers and purchase returns reduce only their source receipt layer', function () {
    $material = costingMaterial();
    $supplier = Supplier::create(['name' => 'FIFO Supplier', 'code' => 'FIFO-SUP', 'status' => 'active']);
    $purchaseOrder = PurchaseOrder::create(['purchase_order_number' => 'FIFO-PO', 'supplier_id' => $supplier->id, 'order_date' => '2026-01-01', 'status' => 'confirmed', 'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'total' => 1000]);
    $purchaseOrderItem = PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, 'material_id' => $material->id, 'quantity' => '100.000', 'unit_price' => '10.00', 'discount' => 0, 'tax' => 0, 'line_total' => 1000]);
    $receipt = GoodsReceipt::create(['receipt_number' => 'FIFO-GR', 'purchase_order_id' => $purchaseOrder->id, 'supplier_id' => $supplier->id, 'received_at' => '2026-01-02', 'received_by' => auth()->id(), 'status' => 'draft']);
    $receipt->items()->create(['purchase_order_item_id' => $purchaseOrderItem->id, 'material_id' => $material->id, 'ordered_quantity' => '100.000', 'received_quantity' => '100.000', 'accepted_quantity' => '100.000', 'rejected_quantity' => '0.000', 'unit_cost' => '10.00']);
    app(ConfirmGoodsReceiptAction::class)->handle($receipt);

    $layer = MaterialCostLayer::query()->where('goods_receipt_id', $receipt->id)->firstOrFail();
    expect((string) $layer->original_quantity)->toBe('100.000')
        ->and((string) $layer->unit_cost)->toBe('10.0000');

    $return = PurchaseReturn::create(['return_number' => 'FIFO-PR', 'supplier_id' => $supplier->id, 'purchase_order_id' => $purchaseOrder->id, 'goods_receipt_id' => $receipt->id, 'return_date' => '2026-01-03', 'reason' => 'اختبار', 'status' => 'draft', 'created_by' => auth()->id()]);
    $return->items()->create(['material_id' => $material->id, 'quantity' => '30.000', 'unit_cost' => '99.00']);
    app(ConfirmPurchaseReturnAction::class)->handle($return);

    expect((string) $layer->fresh()->remaining_quantity)->toBe('70.000')
        ->and((string) $layer->fresh()->unit_cost)->toBe('10.0000')
        ->and((string) MaterialCostAllocation::query()->where('reference_type', PurchaseReturn::class)->where('reference_id', $return->id)->sum('total_cost'))->toBe('300.00');
});

it('restores sales returns at their original historical cost and reverses them exactly', function () {
    ['variant' => $variant, 'customer' => $customer] = costingSalesFixture();
    $layer = costingProductLayer($variant, '100.000', '320.0000', '2026-01-01');
    // Align physical inventory with this single layer for the scenario.
    $variant->update(['quantity' => '100.000']);
    $invoice = SalesInvoice::create(['invoice_number' => 'FIFO-RETURN-INV', 'customer_id' => $customer->id, 'invoice_date' => '2026-01-02', 'status' => SalesInvoiceStatus::DRAFT, 'total_quantity' => 20, 'subtotal' => 10000, 'discount' => 0, 'total_amount' => 10000]);
    $invoiceItem = $invoice->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'sku' => $variant->sku, 'quantity' => 20, 'unit_price' => 500, 'line_total' => 10000]);
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);
    $return = SalesReturn::create(['return_number' => 'FIFO-SR', 'sales_invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'return_date' => '2026-01-03', 'status' => 'draft', 'reason' => 'اختبار', 'total_quantity' => 5, 'total_amount' => 2500, 'created_by' => auth()->id()]);
    $return->items()->create(['sales_invoice_item_id' => $invoiceItem->id, 'product_variant_id' => $variant->id, 'product_name' => $invoiceItem->product_name, 'sku' => $variant->sku, 'quantity' => 5, 'unit_price' => 500, 'line_total' => 2500]);

    app(ConfirmSalesReturnAction::class)->handle($return);
    expect((string) $return->fresh()->total_cogs)->toBe('1600.00')
        ->and((string) $layer->fresh()->remaining_quantity)->toBe('85.000');

    app(CancelSalesReturnAction::class)->handle($return);
    expect((string) $layer->fresh()->remaining_quantity)->toBe('80.000')
        ->and(ProductCostAllocation::query()->where('reference_type', SalesReturn::class)->where('reference_id', $return->id)->count())->toBe(2);
});
