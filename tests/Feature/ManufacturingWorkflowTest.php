<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\Bom\UpdateBomAction;
use App\Actions\MaterialInventory\CreateMaterialOpeningStockAction;
use App\Actions\ProductionDefect\RecordDefectAction;
use App\Actions\ProductionDefect\ReviewDefectAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialReturnAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionMaterial\RecordWasteAction;
use App\Actions\ProductionMaterial\ReviewWasteAction;
use App\Actions\ProductionOrder\CancelProductionOrderAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\HoldProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\ProductionOrder\ResumeProductionOrderAction;
use App\Actions\ProductionRework\CompleteReworkAction;
use App\Actions\ProductionRework\CreateReworkAction;
use App\Actions\ProductionRework\StartReworkAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Enums\BomStatus;
use App\Enums\MaterialStockMovementType;
use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Color;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Worker;
use App\Services\MaterialStockService;
use App\Services\ProductionSummaryService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

function manufacturingFixture(): array
{
    $category = Category::create(['code' => 'MFG-CAT', 'name' => 'Manufacturing', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Abaya Model A', 'code' => 'MFG-P', 'base_cost_price' => 100, 'cost_price' => 100, 'status' => 'active']);
    $color = Color::create(['name' => 'Black MFG', 'code' => 'MFG-BLK', 'status' => 'active']);
    $size = Size::create(['name' => 'Large MFG', 'code' => 'MFG-L', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'MFG-SKU', 'barcode' => 'MFG-BAR', 'default_price' => 0, 'quantity' => 0, 'status' => 'active']);
    $material = Material::create(['code' => 'MAT-FAB', 'name' => 'Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'status' => 'active']);
    $operation = ManufacturingOperation::create(['name' => 'Cutting MFG', 'default_unit_cost' => 10, 'status' => 'active']);
    $worker = Worker::create(['code' => 'WRK-TEST', 'name' => 'Ahmed Worker', 'status' => 'active']);

    return compact('product', 'variant', 'material', 'operation', 'worker');
}

function manufacturingBom(array $fixture): Bom
{
    $bom = app(CreateBomAction::class)->handle(['product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'basis_quantity' => '1.000', 'items' => [['material_id' => $fixture['material']->id, 'quantity' => '3.000']], 'operations' => [['manufacturing_operation_id' => $fixture['operation']->id]]]);

    return app(ActivateBomAction::class)->handle($bom);
}

function manufacturingOrder(array $fixture, Bom $bom, string $assigned = '10.000', string $allocated = '30.000'): ProductionOrder
{
    return app(CreateProductionOrderAction::class)->handle(['product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'bom_id' => $bom->id, 'planned_quantity' => '10.000', 'planned_start_date' => now()->toDateString(), 'assignments' => [['bom_operation_id' => $bom->operations->first()->id, 'worker_id' => $fixture['worker']->id, 'assigned_quantity' => $assigned, 'materials' => [$bom->items->first()->id => ['allocated_quantity' => $allocated]]]]]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the production order creation wizard with prepared BOM and worker data', function () {
    $fixture = manufacturingFixture();
    $bom = manufacturingBom($fixture);

    $this->get(route('production-orders.create'))
        ->assertOk()
        ->assertSee($bom->bom_number)
        ->assertSee($fixture['worker']->code)
        ->assertSee('const boms =', false)
        ->assertSee('const workers =', false);
});

it('versions active BOMs and preserves the original snapshot', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    $next = app(UpdateBomAction::class)->handle($bom, ['product_id' => $f['product']->id, 'product_variant_id' => $f['variant']->id, 'basis_quantity' => '1.000', 'items' => [['material_id' => $f['material']->id, 'quantity' => '3.250']], 'operations' => [['manufacturing_operation_id' => $f['operation']->id]]]);
    expect($next->bom_number)->toBe($bom->bom_number)->and($next->version)->toBe(2)->and($next->status)->toBe(BomStatus::DRAFT)->and((string) $bom->fresh()->items->first()->quantity)->toBe('3.000');
});

it('rolls back invalid assignment and allocation totals', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    expect(fn () => manufacturingOrder($f, $bom, '9.000', '30.000'))->toThrow(ValidationException::class);
    expect(ProductionOrder::count())->toBe(0);
    expect(fn () => manufacturingOrder($f, $bom, '10.000', '29.000'))->toThrow(ValidationException::class);
    expect(ProductionOrder::count())->toBe(0);
});

it('snapshots operation costs and adds them to the production order cost', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    $order = manufacturingOrder($f, $bom);

    expect((string) $bom->operations->first()->unit_cost)->toBe('10.00')
        ->and((string) $f['product']->fresh()->cost_price)->toBe('110.00')
        ->and((string) $order->base_unit_cost)->toBe('100.00')
        ->and((string) $order->operations_unit_cost)->toBe('10.00')
        ->and((string) $order->estimated_unit_cost)->toBe('110.00')
        ->and((string) $order->estimated_total_cost)->toBe('1100.00')
        ->and((string) $order->operations->first()->total_cost)->toBe('100.00');

    $f['operation']->update(['default_unit_cost' => 25]);

    expect((string) $bom->fresh()->operations->first()->unit_cost)->toBe('10.00')
        ->and((string) $order->fresh()->estimated_unit_cost)->toBe('110.00');
});

it('runs material accountability and finished goods posting without double deduction', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $f['material']->id, 'quantity' => '100.000', 'movement_date' => now()->toDateString()]);
    MaterialCostLayer::query()->where('material_id', $f['material']->id)->update([
        'unit_cost' => '10.0000',
        'total_original_cost' => '1000.00',
    ]);
    $order = manufacturingOrder($f, $bom);
    expect($order->order_number)->toStartWith('MO')->and($order->product_name)->toBe('Abaya Model A')->and($order->bom_version)->toBe(1);
    $confirm = app(ConfirmProductionOrderAction::class);
    $confirm->handle($order);
    $confirm->handle($order);
    $assignment = $order->fresh()->workAssignments()->first();
    $allocation = $assignment->materials()->first();
    $event = fn (string $quantity) => ['quantity' => $quantity, 'occurred_at' => now()];
    $issue = app(IssueProductionMaterialsAction::class)->handle($allocation, $event('30.000'));
    app(StartWorkAssignmentAction::class)->handle($assignment);
    app(RecordMaterialUsageAction::class)->handle($allocation, $event('28.000'));
    app(RecordMaterialReturnAction::class)->handle($allocation, $event('1.000'));
    $waste = app(RecordWasteAction::class)->handle($allocation, $event('1.000'));
    app(ReviewWasteAction::class)->handle($waste, true);
    $defect = app(RecordDefectAction::class)->handle($assignment, ['quantity' => '1.000', 'reason' => 'wrong_cutting']);
    app(ReviewDefectAction::class)->handle($defect, true);
    app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => '10.000', 'good_quantity' => '9.000', 'defective_quantity' => '1.000']);
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '9.000', 'output_date' => now()->toDateString()]);
    app(CompleteProductionOrderAction::class)->handle($order);

    expect(app(MaterialStockService::class)->getCurrentStock($f['material']))->toBe('71.000')
        ->and(MaterialStockMovement::where('movement_type', MaterialStockMovementType::PRODUCTION_ISSUE)->count())->toBe(1)
        ->and(MaterialStockMovement::where('movement_type', MaterialStockMovementType::PRODUCTION_RETURN)->count())->toBe(1)
        ->and(app(StockService::class)->getCurrentStock($f['variant']))->toBe('9.000')
        ->and(StockMovement::where('movement_type', StockMovementType::PRODUCTION_OUTPUT)->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(ProductionOrderStatus::COMPLETED)
        ->and((string) $order->fresh()->final_total_cost)->toBe('390.00')
        ->and((string) $order->fresh()->final_unit_cost)->toBe('43.3333')
        ->and((string) $order->outputs()->first()->unit_cost)->toBe('43.3333')
        ->and((string) $f['product']->fresh()->cost_price)->toBe('110.00')
        ->and((string) $f['variant']->fresh()->actual_cost)->toBe('43.3333')
        ->and((string) $f['product']->fresh()->base_cost_price)->toBe('100.00')
        ->and($assignment->fresh()->worker_id)->toBe($f['worker']->id)
        ->and($waste->fresh()->recorded_by)->toBe(auth()->id())
        ->and($waste->fresh()->reviewed_by)->toBe(auth()->id());

    $this->get(route('production-orders.show', $order))->assertOk()->assertSee($order->order_number);
    $this->get(route('work-assignments.show', $assignment))->assertOk()->assertSee($f['worker']->name);
    $this->get(route('production-orders.print', $order))->assertOk()->assertSee($order->order_number);
    $this->get(route('production-material-transactions.print', $issue))->assertOk()->assertSee($f['worker']->name);
    $this->get(route('production-reports.material-variance'))->assertOk()->assertSee($order->order_number);
});

it('supports hold resume cancellation and blocks cancellation with unreturned material', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $f['material']->id, 'quantity' => '50.000', 'movement_date' => now()->toDateString()]);
    $order = manufacturingOrder($f, $bom);
    app(ConfirmProductionOrderAction::class)->handle($order);
    app(HoldProductionOrderAction::class)->handle($order);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::ON_HOLD);
    app(ResumeProductionOrderAction::class)->handle($order);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::CONFIRMED);
    $allocation = $order->workAssignments()->first()->materials()->first();
    $data = ['quantity' => '30.000', 'occurred_at' => now()];
    app(IssueProductionMaterialsAction::class)->handle($allocation, $data);
    expect(fn () => app(CancelProductionOrderAction::class)->handle($order))->toThrow(ValidationException::class);
    app(RecordMaterialReturnAction::class)->handle($allocation, $data);
    app(CancelProductionOrderAction::class)->handle($order);
    app(CancelProductionOrderAction::class)->handle($order);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::CANCELLED)->and(app(MaterialStockService::class)->getCurrentStock($f['material']))->toBe('50.000');
});

it('reviews defects and completes rework into valid final output', function () {
    $f = manufacturingFixture();
    $bom = manufacturingBom($f);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $f['material']->id, 'quantity' => '100.000', 'movement_date' => now()->toDateString()]);
    MaterialCostLayer::query()->where('material_id', $f['material']->id)->update([
        'unit_cost' => '10.0000',
        'total_original_cost' => '1000.00',
    ]);
    $order = manufacturingOrder($f, $bom);
    app(ConfirmProductionOrderAction::class)->handle($order);
    $assignment = $order->workAssignments()->first();
    $allocation = $assignment->materials()->first();
    $event = fn ($q) => ['quantity' => $q, 'occurred_at' => now()];
    app(IssueProductionMaterialsAction::class)->handle($allocation, $event('30.000'));
    app(StartWorkAssignmentAction::class)->handle($assignment);
    app(RecordMaterialUsageAction::class)->handle($allocation, $event('30.000'));
    $rejected = app(RecordDefectAction::class)->handle($assignment, ['quantity' => '1.000', 'reason' => 'other']);
    app(ReviewDefectAction::class)->handle($rejected, false);
    expect($rejected->fresh()->status)->toBe(ProductionDefectStatus::REJECTED);
    $defect = app(RecordDefectAction::class)->handle($assignment, ['quantity' => '2.000', 'reason' => 'wrong_size']);
    $rework = app(CreateReworkAction::class)->handle($defect, ['production_order_operation_id' => $assignment->production_order_operation_id, 'worker_id' => $f['worker']->id, 'assigned_quantity' => '2.000']);
    app(StartReworkAction::class)->handle($rework);
    app(CompleteReworkAction::class)->handle($rework, ['good_quantity' => '2.000', 'defective_quantity' => '0.000']);
    app(CompleteWorkAssignmentAction::class)->handle($assignment, ['produced_quantity' => '10.000', 'good_quantity' => '8.000', 'defective_quantity' => '2.000']);
    $summary = app(ProductionSummaryService::class)->orderSummary($order->fresh(['workAssignments', 'operations']));
    expect($summary['good'])->toBe('10.000');
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '5.000', 'output_date' => now()->toDateString()]);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::PARTIALLY_COMPLETED);
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '5.000', 'output_date' => now()->toDateString()]);
    app(CompleteProductionOrderAction::class)->handle($order);
    expect(app(StockService::class)->getCurrentStock($f['variant']))->toBe('10.000');
});
