<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\Bom\UpdateBomAction;
use App\Actions\MaterialInventory\CreateMaterialOpeningStockAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Http\Controllers\ProductionOrderController;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\BomOperation;
use App\Models\Category;
use App\Models\Color;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterialOperation;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\Worker;
use App\Services\MaterialStockService;
use Illuminate\Validation\ValidationException;

function allocationFixture(): array
{
    $category = Category::create(['code' => 'ALLOC-CAT', 'name' => 'Allocation', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Allocated Abaya', 'code' => 'ALLOC-P', 'cost_price' => 0, 'status' => 'active']);
    $color = Color::create(['name' => 'Allocation Black', 'code' => 'ALLOC-BLK', 'status' => 'active']);
    $size = Size::create(['name' => 'Allocation Large', 'code' => 'ALLOC-L', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'ALLOC-SKU', 'barcode' => 'ALLOC-BAR', 'default_price' => 0, 'quantity' => 0, 'status' => 'active']);
    $fabric = Material::create(['code' => 'ALLOC-FAB', 'name' => 'Allocation Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'status' => 'active']);
    $thread = Material::create(['code' => 'ALLOC-THR', 'name' => 'Allocation Thread', 'category' => 'thread', 'unit' => 'roll', 'minimum_stock' => 0, 'quantity' => 0, 'status' => 'active']);
    $cutting = ManufacturingOperation::create(['name' => 'Allocation Cutting', 'status' => 'active']);
    $sewing = ManufacturingOperation::create(['name' => 'Allocation Sewing', 'status' => 'active']);
    $cutter = Worker::create(['code' => 'ALLOC-W1', 'name' => 'Allocation Cutter', 'status' => 'active']);
    $sewer = Worker::create(['code' => 'ALLOC-W2', 'name' => 'Allocation Sewer', 'status' => 'active']);

    return compact('product', 'variant', 'fabric', 'thread', 'cutting', 'sewing', 'cutter', 'sewer');
}

function allocationBom(array $fixture, bool $splitFabric = false, bool $legacy = false)
{
    $fabricAllocations = $splitFabric
        ? [
            ['manufacturing_operation_id' => $fixture['cutting']->id, 'quantity' => '3.000'],
            ['manufacturing_operation_id' => $fixture['sewing']->id, 'quantity' => '0.200'],
        ]
        : [['manufacturing_operation_id' => $fixture['cutting']->id, 'quantity' => '3.200']];

    $items = [
        ['material_id' => $fixture['fabric']->id, 'quantity' => '3.200', 'operation_allocations' => $fabricAllocations],
        ['material_id' => $fixture['thread']->id, 'quantity' => '0.080', 'operation_allocations' => [['manufacturing_operation_id' => $fixture['sewing']->id, 'quantity' => '0.080']]],
    ];
    if ($legacy) {
        $items = collect($items)->map(fn ($item) => collect($item)->except('operation_allocations')->all())->all();
    }

    $bom = app(CreateBomAction::class)->handle([
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'basis_quantity' => '1.000',
        'items' => $items,
        'operations' => [
            ['manufacturing_operation_id' => $fixture['cutting']->id],
            ['manufacturing_operation_id' => $fixture['sewing']->id],
        ],
    ]);

    return app(ActivateBomAction::class)->handle($bom);
}

function allocationOrderData(array $fixture, $bom, bool $splitFabric = false): array
{
    $fabricItem = $bom->items->firstWhere('material_id', $fixture['fabric']->id);
    $threadItem = $bom->items->firstWhere('material_id', $fixture['thread']->id);
    $cutting = $bom->operations->firstWhere('manufacturing_operation_id', $fixture['cutting']->id);
    $sewing = $bom->operations->firstWhere('manufacturing_operation_id', $fixture['sewing']->id);

    return [
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'bom_id' => $bom->id,
        'planned_quantity' => '10.000',
        'planned_start_date' => now()->toDateString(),
        'assignments' => [
            [
                'bom_operation_id' => $cutting->id,
                'worker_id' => $fixture['cutter']->id,
                'assigned_quantity' => '10.000',
                'materials' => [$fabricItem->id => ['allocated_quantity' => $splitFabric ? '30.000' : '32.000']],
            ],
            [
                'bom_operation_id' => $sewing->id,
                'worker_id' => $fixture['sewer']->id,
                'assigned_quantity' => '10.000',
                'materials' => array_filter([
                    $fabricItem->id => $splitFabric ? ['allocated_quantity' => '2.000'] : null,
                    $threadItem->id => ['allocated_quantity' => '0.800'],
                ]),
            ],
        ],
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('maps a BOM material to one operation and prepares only related wizard data', function () {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture);
    $fabric = $bom->items->firstWhere('material_id', $fixture['fabric']->id);
    $cutting = $bom->operations->firstWhere('manufacturing_operation_id', $fixture['cutting']->id);

    expect($fabric->operationAllocations)->toHaveCount(1)
        ->and($fabric->operationAllocations->first()->bom_operation_id)->toBe($cutting->id);

    $view = app(ProductionOrderController::class)->create(app(MaterialStockService::class));
    $wizardBom = collect($view->getData()['bomWizardData'])->firstWhere('id', $bom->id);
    $wizardFabric = collect($wizardBom['items'])->firstWhere('id', $fabric->id);
    expect($wizardFabric['operations'])->toHaveCount(1)
        ->and($wizardFabric['operations'][0]['bom_operation_id'])->toBe($cutting->id);

    $this->get(route('boms.create'))->assertOk();
    $this->get(route('boms.edit', $bom))->assertOk()->assertSee('عمليات الاستهلاك');
});

it('rejects a BOM material distribution whose operation quantities do not match the item quantity', function () {
    $fixture = allocationFixture();

    expect(fn () => app(CreateBomAction::class)->handle([
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'basis_quantity' => '1.000',
        'items' => [[
            'material_id' => $fixture['fabric']->id,
            'quantity' => '3.200',
            'operation_allocations' => [['manufacturing_operation_id' => $fixture['cutting']->id, 'quantity' => '3.000']],
        ]],
        'operations' => [['manufacturing_operation_id' => $fixture['cutting']->id]],
    ]))->toThrow(ValidationException::class);

    expect(Bom::count())->toBe(0)
        ->and(BomItem::count())->toBe(0)
        ->and(BomOperation::count())->toBe(0);
});

it('splits one BOM material across multiple operations and stores an immutable snapshot', function () {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture, true);
    $order = app(CreateProductionOrderAction::class)->handle(allocationOrderData($fixture, $bom, true));
    $fabric = $order->materials()->where('material_id', $fixture['fabric']->id)->firstOrFail();
    $snapshots = $fabric->operationRequirements()->with('productionOrderOperation')->get()->keyBy(fn ($row) => $row->productionOrderOperation->manufacturing_operation_id);

    expect($snapshots)->toHaveCount(2)
        ->and((string) $snapshots[$fixture['cutting']->id]->required_quantity)->toBe('30.000')
        ->and((string) $snapshots[$fixture['sewing']->id]->required_quantity)->toBe('2.000');

    app(UpdateBomAction::class)->handle($bom, [
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'basis_quantity' => '1.000',
        'items' => [
            ['material_id' => $fixture['fabric']->id, 'quantity' => '3.200', 'operation_allocations' => [['manufacturing_operation_id' => $fixture['cutting']->id, 'quantity' => '3.200']]],
            ['material_id' => $fixture['thread']->id, 'quantity' => '0.080', 'operation_allocations' => [['manufacturing_operation_id' => $fixture['sewing']->id, 'quantity' => '0.080']]],
        ],
        'operations' => [
            ['manufacturing_operation_id' => $fixture['cutting']->id],
            ['manufacturing_operation_id' => $fixture['sewing']->id],
        ],
    ]);

    expect($fabric->operationRequirements()->count())->toBe(2)
        ->and((string) $fabric->operationRequirements()->sum('required_quantity'))->toBe('32.000');
});

it('accepts exact allocation and rejects partial over and unrelated operation allocations atomically', function (string $case) {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture);
    $data = allocationOrderData($fixture, $bom);
    $fabricId = $bom->items->firstWhere('material_id', $fixture['fabric']->id)->id;
    $threadId = $bom->items->firstWhere('material_id', $fixture['thread']->id)->id;

    if ($case === 'partial') {
        $data['assignments'][0]['materials'][$fabricId]['allocated_quantity'] = '31.000';
    }
    if ($case === 'over') {
        $data['assignments'][0]['materials'][$fabricId]['allocated_quantity'] = '33.000';
    }
    if ($case === 'unrelated') {
        $data['assignments'][0]['materials'][$threadId] = ['allocated_quantity' => '0.100'];
    }

    expect(fn () => app(CreateProductionOrderAction::class)->handle($data))->toThrow(ValidationException::class);
    expect(ProductionOrder::count())->toBe(0)
        ->and(WorkAssignment::count())->toBe(0)
        ->and(ProductionOrderMaterialOperation::count())->toBe(0);
})->with(['partial', 'over', 'unrelated']);

it('supports legacy BOM fallback without operation mappings', function () {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture, legacy: true);
    $firstOperation = $bom->operations->sortBy('sequence')->first();
    expect($bom->items->every(fn ($item) => $item->operationAllocations->first()->bom_operation_id === $firstOperation->id))->toBeTrue();

    $data = allocationOrderData($fixture, $bom);
    $threadId = $bom->items->firstWhere('material_id', $fixture['thread']->id)->id;
    unset($data['assignments'][1]['materials'][$threadId]);
    $data['assignments'][0]['materials'][$threadId] = ['allocated_quantity' => '0.800'];
    expect(app(CreateProductionOrderAction::class)->handle($data))->toBeInstanceOf(ProductionOrder::class);
});

it('issues against the correct operation allocation and never deducts on allocation or usage', function () {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture, true);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $fixture['fabric']->id, 'quantity' => '100.000', 'movement_date' => now()->toDateString()]);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $fixture['thread']->id, 'quantity' => '10.000', 'movement_date' => now()->toDateString()]);
    $order = app(CreateProductionOrderAction::class)->handle(allocationOrderData($fixture, $bom, true));
    expect(app(MaterialStockService::class)->getCurrentStock($fixture['fabric']))->toBe('100.000');

    app(ConfirmProductionOrderAction::class)->handle($order);
    expect(app(MaterialStockService::class)->getCurrentStock($fixture['fabric']))->toBe('100.000');

    $cuttingAssignment = $order->workAssignments()->whereHas('operation', fn ($query) => $query->where('manufacturing_operation_id', $fixture['cutting']->id))->firstOrFail();
    $allocation = $cuttingAssignment->materials()->where('production_order_material_id', $order->materials()->where('material_id', $fixture['fabric']->id)->value('id'))->firstOrFail();
    $event = ['quantity' => '30.000', 'occurred_at' => now()];
    $transaction = app(IssueProductionMaterialsAction::class)->handle($allocation, $event);
    expect($transaction->work_assignment_id)->toBe($cuttingAssignment->id)
        ->and($transaction->worker_id)->toBe($fixture['cutter']->id)
        ->and(app(MaterialStockService::class)->getCurrentStock($fixture['fabric']))->toBe('70.000');

    app(RecordMaterialUsageAction::class)->handle($allocation, $event);
    expect(app(MaterialStockService::class)->getCurrentStock($fixture['fabric']))->toBe('70.000');
});

it('keeps legacy production order allocations usable when the new snapshot link is absent', function () {
    $fixture = allocationFixture();
    $bom = allocationBom($fixture);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $fixture['fabric']->id, 'quantity' => '50.000', 'movement_date' => now()->toDateString()]);
    app(CreateMaterialOpeningStockAction::class)->handle(['material_id' => $fixture['thread']->id, 'quantity' => '10.000', 'movement_date' => now()->toDateString()]);
    $order = app(CreateProductionOrderAction::class)->handle(allocationOrderData($fixture, $bom));
    app(ConfirmProductionOrderAction::class)->handle($order);
    $allocation = $order->workAssignments()->whereHas('operation', fn ($query) => $query->where('manufacturing_operation_id', $fixture['cutting']->id))->firstOrFail()->materials()->firstOrFail();
    $allocation->update(['production_order_material_operation_id' => null]);

    app(IssueProductionMaterialsAction::class)->handle($allocation, ['quantity' => '32.000', 'occurred_at' => now()]);
    $this->get(route('production-orders.show', $order))->assertOk();
    expect(app(MaterialStockService::class)->getCurrentStock($fixture['fabric']))->toBe('18.000');
});
