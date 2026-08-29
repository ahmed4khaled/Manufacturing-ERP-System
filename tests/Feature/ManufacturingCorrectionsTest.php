<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\ProductionDefect\RecordDefectAction;
use App\Actions\ProductionDefect\ReviewDefectAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Enums\ProductionOrderStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryCostingService;
use App\Services\MaterialStockService;
use App\Services\ProductionSummaryService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/** @return array<string, mixed> */
function correctedManufacturingFixture(int $operationCount = 1, ?string $standardUnitCost = '7.5000'): array
{
    $category = Category::create(['code' => 'FIX-CAT', 'name' => 'Fixed Manufacturing', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Fixed Abaya', 'code' => 'FIX-P', 'base_cost_price' => 100, 'cost_price' => 100, 'status' => 'active']);
    $color = Color::create(['name' => 'Fixed Black', 'code' => 'FIX-BLK', 'status' => 'active']);
    $size = Size::create(['name' => 'Fixed Size', 'code' => 'FIX-S', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'FIX-SKU', 'barcode' => 'FIX-BAR', 'default_price' => 500, 'quantity' => 0, 'status' => 'active']);
    $material = Material::create(['code' => 'FIX-MAT', 'name' => 'Fixed Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'standard_unit_cost' => $standardUnitCost, 'status' => 'active']);
    $worker = Worker::create(['code' => 'FIX-W', 'name' => 'Fixed Worker', 'status' => 'active']);
    $operations = collect();
    for ($index = 1; $index <= $operationCount; $index++) {
        $operations->push(ManufacturingOperation::create([
            'name' => "Fixed Operation {$index}",
            'default_unit_cost' => 10,
            'status' => 'active',
        ]));
    }

    $bom = app(CreateBomAction::class)->handle([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'basis_quantity' => '1.000',
        'items' => [['material_id' => $material->id, 'quantity' => '3.000']],
        'operations' => $operations->map(fn ($operation) => ['manufacturing_operation_id' => $operation->id])->all(),
    ]);
    $bom = app(ActivateBomAction::class)->handle($bom);

    return compact('product', 'variant', 'material', 'worker', 'operations', 'bom');
}

function correctedManufacturingOrder(array $fixture, string $planned = '5.000')
{
    $assignments = $fixture['bom']->operations->map(function ($operation, int $index) use ($fixture, $planned) {
        $assignment = [
            'bom_operation_id' => $operation->id,
            'worker_id' => $fixture['worker']->id,
            'assigned_quantity' => $planned,
            'materials' => [],
        ];
        if ($index === 0) {
            $assignment['materials'][$fixture['bom']->items->first()->id] = [
                'allocated_quantity' => bcmul($planned, '3.000', 3),
            ];
        }

        return $assignment;
    })->all();

    return app(CreateProductionOrderAction::class)->handle([
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'bom_id' => $fixture['bom']->id,
        'planned_quantity' => $planned,
        'planned_start_date' => now()->toDateString(),
        'assignments' => $assignments,
    ]);
}

function correctedMaterialTransaction($order, ProductionMaterialTransactionType $type, ?string $actualCost): ProductionMaterialTransaction
{
    $assignment = $order->workAssignments()->firstOrFail();
    $allocation = $assignment->materials()->firstOrFail();

    return $order->materialTransactions()->create([
        'work_assignment_id' => $assignment->id,
        'work_assignment_material_id' => $allocation->id,
        'worker_id' => $assignment->worker_id,
        'material_id' => $allocation->productionOrderMaterial->material_id,
        'type' => $type,
        'quantity' => $type === ProductionMaterialTransactionType::RETURN ? '20.000' : '100.000',
        'actual_cost' => $actualCost,
        'status' => ProductionMaterialTransactionStatus::RECORDED,
        'occurred_at' => now(),
        'recorded_by' => auth()->id(),
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the current valued FIFO material cost for an open production order', function () {
    $order = tap(correctedManufacturingOrder(correctedManufacturingFixture()), fn ($order) => $order->update(['status' => ProductionOrderStatus::CONFIRMED]));
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::ISSUE, '1000.00');

    expect(app(InventoryCostingService::class)->currentProductionMaterialCost($order))->toBe('1000.00');

    Permission::findOrCreate('production_orders.view', 'web');
    auth()->user()->givePermissionTo('production_orders.view');
    $this->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertViewHas('displayMaterialCost', '1000.00')
        ->assertViewHas('materialCostLabel', 'تكلفة خامات FIFO الحالية');
});

it('subtracts valued returns from the current FIFO material cost', function () {
    $order = tap(correctedManufacturingOrder(correctedManufacturingFixture()), fn ($order) => $order->update(['status' => ProductionOrderStatus::IN_PROGRESS]));
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::ISSUE, '1000.00');
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::RETURN, '200.00');

    expect(app(InventoryCostingService::class)->currentProductionMaterialCost($order))->toBe('800.00');
});

it('marks the current FIFO material cost unvalued when an issue is unvalued', function () {
    $order = tap(correctedManufacturingOrder(correctedManufacturingFixture()), fn ($order) => $order->update(['status' => ProductionOrderStatus::PARTIALLY_COMPLETED]));
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::ISSUE, null);

    expect(app(InventoryCostingService::class)->currentProductionMaterialCost($order))->toBeNull();

    Permission::findOrCreate('production_orders.view', 'web');
    auth()->user()->givePermissionTo('production_orders.view');
    $this->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertViewHas('displayMaterialCost', null);
});

it('ignores an unvalued usage when calculating current FIFO material cost', function () {
    $order = tap(correctedManufacturingOrder(correctedManufacturingFixture()), fn ($order) => $order->update(['status' => ProductionOrderStatus::IN_PROGRESS]));
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::ISSUE, '1000.00');
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::USAGE, null);

    expect(app(InventoryCostingService::class)->currentProductionMaterialCost($order))->toBe('1000.00');
});

it('shows the stored final material cost snapshot for a completed production order', function () {
    $order = tap(correctedManufacturingOrder(correctedManufacturingFixture()), fn ($order) => $order->update([
        'status' => ProductionOrderStatus::COMPLETED,
        'actual_material_cost' => '777.00',
    ]));
    correctedMaterialTransaction($order, ProductionMaterialTransactionType::ISSUE, '1000.00');

    Permission::findOrCreate('production_orders.view', 'web');
    auth()->user()->givePermissionTo('production_orders.view');
    $this->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertViewHas('displayMaterialCost', '777.00')
        ->assertViewHas('materialCostLabel', 'تكلفة خامات FIFO الفعلية النهائية');

    expect((string) $order->fresh()->actual_material_cost)->toBe('777.00');
});

it('separates order assigned quantity from total work assignment load', function () {
    $fixture = correctedManufacturingFixture(4);
    $order = correctedManufacturingOrder($fixture);
    $summary = app(ProductionSummaryService::class)->orderSummary($order->fresh(['workAssignments', 'operations']));

    expect($summary['planned'])->toBe('5.000')
        ->and($summary['assigned'])->toBe('5.000')
        ->and($summary['work_assignment_load'])->toBe('20.000');

    Permission::findOrCreate('production_orders.view', 'web');
    auth()->user()->givePermissionTo('production_orders.view');
    $this->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee('كمية الإنتاج المسندة')
        ->assertSee('إجمالي حمل تكليفات العمليات')
        ->assertSee('تكلفة العمليات القياسية (Snapshot)');
});

it('calculates and preserves BOM material standard cost snapshots', function () {
    $fixture = correctedManufacturingFixture();
    $order = correctedManufacturingOrder($fixture);
    $orderMaterial = $order->materials()->firstOrFail();

    expect((string) $fixture['bom']->items->first()->standard_unit_cost)->toBe('7.5000')
        ->and((string) $orderMaterial->standard_unit_cost)->toBe('7.5000')
        ->and((string) $orderMaterial->standard_total_cost)->toBe('112.50')
        ->and((string) $order->standard_material_cost)->toBe('112.50')
        ->and($order->standard_material_cost_source)->toBe('bom')
        ->and((string) $order->base_unit_cost)->toBe('22.50')
        ->and((string) $order->estimated_total_cost)->toBe('162.50');

    $fixture['material']->update(['standard_unit_cost' => '99.0000']);

    expect((string) $fixture['bom']->fresh()->items->first()->standard_unit_cost)->toBe('7.5000')
        ->and((string) $order->fresh()->standard_material_cost)->toBe('112.50')
        ->and((string) $order->materials()->first()->standard_total_cost)->toBe('112.50');
});

it('does not allow a later operation to process more than previous-stage good quantity', function () {
    $fixture = correctedManufacturingFixture(2);
    $order = correctedManufacturingOrder($fixture);
    app(MaterialStockService::class)->createOpeningStock(['material_id' => $fixture['material']->id, 'quantity' => '15.000', 'movement_date' => now()->toDateString()]);
    app(ConfirmProductionOrderAction::class)->handle($order);
    $assignments = $order->workAssignments()->with('operation')->get()->sortBy('operation.sequence')->values();

    app(StartWorkAssignmentAction::class)->handle($assignments[0]);
    $defect = app(RecordDefectAction::class)->handle($assignments[0], ['quantity' => '1.000', 'reason' => 'other']);
    app(ReviewDefectAction::class)->handle($defect, true);
    app(CompleteWorkAssignmentAction::class)->handle($assignments[0], ['produced_quantity' => '5.000', 'good_quantity' => '4.000', 'defective_quantity' => '1.000']);
    app(StartWorkAssignmentAction::class)->handle($assignments[1]);

    expect(fn () => app(CompleteWorkAssignmentAction::class)->handle($assignments[1], ['produced_quantity' => '5.000', 'good_quantity' => '5.000', 'defective_quantity' => '0.000']))
        ->toThrow(ValidationException::class);

    app(CompleteWorkAssignmentAction::class)->handle($assignments[1], ['produced_quantity' => '4.000', 'good_quantity' => '4.000', 'defective_quantity' => '0.000']);
    expect((string) $assignments[1]->fresh()->produced_quantity)->toBe('4.000');
});

it('blocks production completion when consumed material FIFO cost is unvalued', function () {
    $fixture = correctedManufacturingFixture();
    $order = correctedManufacturingOrder($fixture);
    app(MaterialStockService::class)->createOpeningStock(['material_id' => $fixture['material']->id, 'quantity' => '15.000', 'movement_date' => now()->toDateString()]);
    app(ConfirmProductionOrderAction::class)->handle($order);
    $assignment = $order->workAssignments()->firstOrFail();
    $allocation = $assignment->materials()->firstOrFail();
    app(IssueProductionMaterialsAction::class)->handle($allocation, ['quantity' => '15.000', 'occurred_at' => now()]);
    app(StartWorkAssignmentAction::class)->handle($assignment);
    app(RecordMaterialUsageAction::class)->handle($allocation, ['quantity' => '15.000', 'occurred_at' => now()]);
    app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => '5.000', 'good_quantity' => '5.000', 'defective_quantity' => '0.000']);
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '5.000', 'output_date' => now()->toDateString()]);

    expect(fn () => app(CompleteProductionOrderAction::class)->handle($order))->toThrow(ValidationException::class)
        ->and($order->fresh()->status)->toBe(ProductionOrderStatus::PARTIALLY_COMPLETED)
        ->and($order->fresh()->final_total_cost)->toBeNull()
        ->and(ProductCostLayer::query()->where('production_order_id', $order->id)->value('is_final'))->toBeFalse();
});

it('allows confirming a sale from an unvalued final layer and records unvalued COGS', function () {
    $fixture = correctedManufacturingFixture();
    $customer = Customer::create(['code' => 'FIX-CUS', 'name' => 'Fixed Customer', 'phone' => '01000000000', 'credit_limit' => 100000, 'status' => 'active']);
    $movement = StockMovement::create(['product_variant_id' => $fixture['variant']->id, 'movement_type' => StockMovementType::OPENING, 'direction' => StockMovementDirection::IN, 'quantity' => '5.000', 'movement_date' => now()->toDateString()]);
    $layer = ProductCostLayer::create(['product_variant_id' => $fixture['variant']->id, 'stock_movement_id' => $movement->id, 'layer_date' => now()->toDateString(), 'original_quantity' => '5.000', 'remaining_quantity' => '5.000', 'unit_cost' => null, 'total_original_cost' => null, 'is_final' => true]);
    $invoice = SalesInvoice::create(['invoice_number' => 'FIX-INV', 'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'status' => SalesInvoiceStatus::DRAFT, 'total_quantity' => 1, 'subtotal' => 500, 'discount' => 0, 'total_amount' => 500]);
    $invoice->items()->create(['product_variant_id' => $fixture['variant']->id, 'product_name' => $fixture['product']->name, 'sku' => $fixture['variant']->sku, 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500]);

    app(ConfirmSalesInvoiceAction::class)->handle($invoice);

    expect($invoice->fresh()->status)->toBe(SalesInvoiceStatus::CONFIRMED)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::SALE)->count())->toBe(1)
        ->and($invoice->fresh()->total_cogs)->toBeNull()
        ->and((string) $layer->fresh()->remaining_quantity)->toBe('4.000')
        ->and(ProductCostAllocation::query()->where('product_cost_layer_id', $layer->id)->value('unit_cost'))->toBeNull();
});
