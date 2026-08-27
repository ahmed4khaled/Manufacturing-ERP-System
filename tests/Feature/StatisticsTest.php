<?php

use App\Models\Bom;
use App\Models\BomOperation;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\ProductionDefect;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionOutput;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Size;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\WorkAssignmentMaterial;
use App\Models\Worker;
use App\Services\MaterialStockService;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function statisticsUser(bool $authorized = true): User
{
    $user = User::factory()->create();
    if ($authorized) {
        Permission::findOrCreate('statistics.view', 'web');
        $user->givePermissionTo('statistics.view');
    }

    return $user;
}

function statisticsFixture(): array
{
    $category = Category::create(['code' => 'STAT-CAT', 'name' => 'Statistics', 'status' => 'active']);
    $productA = Product::create(['category_id' => $category->id, 'code' => 'STAT-A', 'name' => 'Abaya A', 'base_cost_price' => 0, 'cost_price' => 0, 'status' => 'active']);
    $productB = Product::create(['category_id' => $category->id, 'code' => 'STAT-B', 'name' => 'Abaya B', 'base_cost_price' => 0, 'cost_price' => 0, 'status' => 'active']);
    $color = Color::create(['code' => 'STAT-BLK', 'name' => 'Black', 'status' => 'active']);
    $size = Size::create(['name' => 'STAT-L', 'sort_order' => 1, 'status' => 'active']);
    $variantA = ProductVariant::create(['product_id' => $productA->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'STAT-A-L', 'barcode' => '1000000000001', 'default_price' => 20, 'quantity' => 0, 'status' => 'active']);
    $variantB = ProductVariant::create(['product_id' => $productB->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'STAT-B-L', 'barcode' => '1000000000002', 'default_price' => 50, 'quantity' => 0, 'status' => 'active']);
    $customerA = Customer::create(['code' => 'STAT-C1', 'name' => 'Customer A', 'phone' => '01000000001', 'status' => 'active']);
    $customerB = Customer::create(['code' => 'STAT-C2', 'name' => 'Customer B', 'phone' => '01000000002', 'status' => 'active']);

    $invoiceA = SalesInvoice::create(['invoice_number' => 'STAT-INV-A', 'customer_id' => $customerA->id, 'invoice_date' => now()->toDateString(), 'status' => 'confirmed', 'total_quantity' => 5, 'subtotal' => 100, 'total_amount' => 100]);
    SalesInvoiceItem::create(['sales_invoice_id' => $invoiceA->id, 'product_variant_id' => $variantA->id, 'product_name' => $productA->name, 'color_name' => 'Black', 'size_name' => 'L', 'sku' => $variantA->sku, 'quantity' => 3, 'unit_price' => 20, 'line_total' => 60]);
    SalesInvoiceItem::create(['sales_invoice_id' => $invoiceA->id, 'product_variant_id' => $variantB->id, 'product_name' => $productB->name, 'color_name' => 'Black', 'size_name' => 'L', 'sku' => $variantB->sku, 'quantity' => 2, 'unit_price' => 20, 'line_total' => 40]);
    $invoiceB = SalesInvoice::create(['invoice_number' => 'STAT-INV-B', 'customer_id' => $customerB->id, 'invoice_date' => now()->toDateString(), 'status' => 'confirmed', 'total_quantity' => 5, 'subtotal' => 250, 'total_amount' => 250]);
    $invoiceBItem = SalesInvoiceItem::create(['sales_invoice_id' => $invoiceB->id, 'product_variant_id' => $variantB->id, 'product_name' => $productB->name, 'color_name' => 'Black', 'size_name' => 'L', 'sku' => $variantB->sku, 'quantity' => 5, 'unit_price' => 50, 'line_total' => 250]);
    $cancelled = SalesInvoice::create(['invoice_number' => 'STAT-INV-X', 'customer_id' => $customerA->id, 'invoice_date' => now()->toDateString(), 'status' => 'cancelled', 'total_quantity' => 50, 'subtotal' => 999, 'total_amount' => 999]);
    SalesInvoiceItem::create(['sales_invoice_id' => $cancelled->id, 'product_variant_id' => $variantA->id, 'product_name' => $productA->name, 'sku' => $variantA->sku, 'quantity' => 50, 'unit_price' => 19.98, 'line_total' => 999]);
    $oldInvoice = SalesInvoice::create(['invoice_number' => 'STAT-INV-OLD', 'customer_id' => $customerA->id, 'invoice_date' => now()->subYear()->toDateString(), 'status' => 'confirmed', 'total_quantity' => 10, 'subtotal' => 500, 'total_amount' => 500]);
    SalesInvoiceItem::create(['sales_invoice_id' => $oldInvoice->id, 'product_variant_id' => $variantA->id, 'product_name' => $productA->name, 'sku' => $variantA->sku, 'quantity' => 10, 'unit_price' => 50, 'line_total' => 500]);

    PaymentReceipt::create(['receipt_number' => 'STAT-PAY-A', 'customer_id' => $customerA->id, 'payment_date' => now()->toDateString(), 'amount' => 30, 'payment_method' => 'cash', 'status' => 'confirmed']);
    PaymentReceipt::create(['receipt_number' => 'STAT-PAY-B', 'customer_id' => $customerB->id, 'payment_date' => now()->toDateString(), 'amount' => 50, 'payment_method' => 'bank_transfer', 'status' => 'confirmed']);
    PaymentReceipt::create(['receipt_number' => 'STAT-PAY-X', 'customer_id' => $customerA->id, 'payment_date' => now()->toDateString(), 'amount' => 900, 'payment_method' => 'cash', 'status' => 'cancelled']);
    CustomerLedgerEntry::create(['customer_id' => $customerA->id, 'reference_type' => 'sales_invoice', 'reference_id' => $invoiceA->id, 'transaction_date' => now(), 'description' => 'Invoice', 'debit' => 100, 'credit' => 0]);
    CustomerLedgerEntry::create(['customer_id' => $customerA->id, 'reference_type' => 'payment_receipt', 'reference_id' => 1, 'transaction_date' => now(), 'description' => 'Payment', 'debit' => 0, 'credit' => 30]);
    CustomerLedgerEntry::create(['customer_id' => $customerB->id, 'reference_type' => 'sales_invoice', 'reference_id' => $invoiceB->id, 'transaction_date' => now(), 'description' => 'Invoice', 'debit' => 250, 'credit' => 0]);
    CustomerLedgerEntry::create(['customer_id' => $customerB->id, 'reference_type' => 'payment_receipt', 'reference_id' => 2, 'transaction_date' => now(), 'description' => 'Payment', 'debit' => 0, 'credit' => 50]);

    $return = SalesReturn::create(['return_number' => 'STAT-RET-A', 'sales_invoice_id' => $invoiceB->id, 'customer_id' => $customerB->id, 'return_date' => now(), 'status' => 'confirmed', 'reason' => 'size', 'total_quantity' => 1, 'total_amount' => 50]);
    SalesReturnItem::create(['sales_return_id' => $return->id, 'sales_invoice_item_id' => $invoiceBItem->id, 'product_variant_id' => $variantB->id, 'product_name' => $productB->name, 'sku' => $variantB->sku, 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50]);
    $cancelledReturn = SalesReturn::create(['return_number' => 'STAT-RET-X', 'sales_invoice_id' => $invoiceB->id, 'customer_id' => $customerB->id, 'return_date' => now(), 'status' => 'cancelled', 'reason' => 'cancelled', 'total_quantity' => 10, 'total_amount' => 500]);
    SalesReturnItem::create(['sales_return_id' => $cancelledReturn->id, 'sales_invoice_item_id' => $invoiceBItem->id, 'product_variant_id' => $variantB->id, 'product_name' => $productB->name, 'sku' => $variantB->sku, 'quantity' => 10, 'unit_price' => 50, 'line_total' => 500]);

    $material = Material::create(['code' => 'STAT-MAT', 'name' => 'Statistics Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 2, 'quantity' => 0, 'status' => 'active']);
    $operation = ManufacturingOperation::create(['name' => 'Statistics Cutting', 'default_unit_cost' => 0, 'status' => 'active']);
    $worker = Worker::create(['code' => 'STAT-W', 'name' => 'Statistics Worker', 'status' => 'active']);
    $bom = Bom::create(['bom_number' => 'STAT-BOM', 'version' => 1, 'product_id' => $productA->id, 'product_variant_id' => $variantA->id, 'basis_quantity' => 1, 'status' => 'active']);
    $bomOperation = BomOperation::create(['bom_id' => $bom->id, 'manufacturing_operation_id' => $operation->id, 'operation_name' => $operation->name, 'unit_cost' => 0, 'sequence' => 1]);
    $order = ProductionOrder::create(['order_number' => 'STAT-PO', 'product_id' => $productA->id, 'product_variant_id' => $variantA->id, 'bom_id' => $bom->id, 'product_name' => $productA->name, 'sku' => $variantA->sku, 'bom_number' => $bom->bom_number, 'bom_version' => 1, 'planned_quantity' => 10, 'planned_start_date' => now(), 'status' => 'in_progress']);
    $orderOperation = ProductionOrderOperation::create(['production_order_id' => $order->id, 'bom_operation_id' => $bomOperation->id, 'manufacturing_operation_id' => $operation->id, 'operation_name' => $operation->name, 'unit_cost' => 0, 'total_cost' => 0, 'sequence' => 1]);
    $assignment = WorkAssignment::create(['production_order_id' => $order->id, 'production_order_operation_id' => $orderOperation->id, 'worker_id' => $worker->id, 'assigned_quantity' => 10, 'produced_quantity' => 8, 'good_quantity' => 7, 'defective_quantity' => 1, 'status' => 'completed']);
    $orderMaterial = ProductionOrderMaterial::create(['production_order_id' => $order->id, 'material_id' => $material->id, 'material_code' => $material->code, 'material_name' => $material->name, 'unit' => 'meter', 'standard_quantity' => 5, 'required_quantity' => 5]);
    $allocation = WorkAssignmentMaterial::create(['work_assignment_id' => $assignment->id, 'production_order_material_id' => $orderMaterial->id, 'standard_quantity' => 5, 'allocated_quantity' => 5]);
    foreach ([['issue', 5, 'approved'], ['usage', 4, 'recorded'], ['return', .5, 'recorded'], ['waste', .5, 'approved'], ['waste', 9, 'rejected']] as [$type, $quantity, $status]) {
        ProductionMaterialTransaction::create(['production_order_id' => $order->id, 'work_assignment_id' => $assignment->id, 'work_assignment_material_id' => $allocation->id, 'worker_id' => $worker->id, 'material_id' => $material->id, 'type' => $type, 'quantity' => $quantity, 'status' => $status, 'occurred_at' => now()]);
    }
    ProductionDefect::create(['production_order_id' => $order->id, 'work_assignment_id' => $assignment->id, 'worker_id' => $worker->id, 'production_order_operation_id' => $orderOperation->id, 'product_variant_id' => $variantA->id, 'quantity' => 1, 'reason' => 'wrong_cutting', 'status' => 'approved']);
    ProductionOutput::create(['production_order_id' => $order->id, 'product_variant_id' => $variantA->id, 'quantity' => 7, 'unit_cost' => 0, 'total_cost' => 0, 'output_date' => now()]);
    MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => 10, 'movement_date' => now()]);
    MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'production_issue', 'direction' => 'out', 'quantity' => 5, 'movement_date' => now()]);

    return compact('productA', 'productB', 'customerA', 'customerB', 'worker', 'material', 'operation', 'variantA');
}

function statisticsData(array $filters = []): array
{
    $service = app(StatisticsService::class);

    return $service->build($service->resolveFilters($filters + ['preset' => 'month']));
}

it('loads for an authorized user and rejects an unauthorized user', function () {
    $this->actingAs(statisticsUser())->get(route('statistics.dashboard'))->assertOk()->assertSee('لوحة الإحصائيات');
    $this->actingAs(statisticsUser(false))->get(route('statistics.dashboard'))->assertForbidden();
});

it('renders every statistics module page from the shared implementation', function (string $routeName, string $heading) {
    $response = $this->actingAs(statisticsUser())->get(route($routeName));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('stats-preset', false)
        ->assertSee('statistics-nav', false);
})->with([
    ['statistics.dashboard', 'لوحة الإحصائيات'],
    ['statistics.sales', 'إحصائيات المبيعات'],
    ['statistics.customers', 'إحصائيات العملاء'],
    ['statistics.collections', 'إحصائيات التحصيلات'],
    ['statistics.purchases', 'إحصائيات المشتريات'],
    ['statistics.inventory', 'إحصائيات المخزون'],
    ['statistics.materials', 'إحصائيات الخامات'],
    ['statistics.manufacturing', 'إحصائيات التصنيع'],
    ['statistics.workers', 'إحصائيات العمال'],
    ['statistics.defects', 'إحصائيات العيوب والهالك'],
    ['statistics.returns', 'إحصائيات المرتجعات'],
]);

it('keeps the statistics sidebar expanded and highlights only the active child', function () {
    $response = $this->actingAs(statisticsUser())->get(route('statistics.sales'));

    $response->assertOk()
        ->assertSee('collapse show', false)
        ->assertSee('href="'.route('statistics.sales').'" class="nav-link sub-link active"', false)
        ->assertSee('href="'.route('statistics.customers').'" class="nav-link sub-link "', false);
});

it('keeps every former single-page statistics section available in its module page', function () {
    statisticsFixture();
    $user = statisticsUser();
    $pages = [
        'statistics.sales' => ['حسب المنتج', 'الألوان والمقاسات', 'الأكثر مبيعًا بالقيمة'],
        'statistics.customers' => ['ترتيب العملاء', 'أعلى الأرصدة المستحقة'],
        'statistics.collections' => ['اتجاه التحصيلات', 'أكبر سندات القبض'],
        'statistics.purchases' => ['اتجاه فواتير الموردين', 'أكثر الخامات شراءً', 'أعلى أرصدة الموردين'],
        'statistics.inventory' => ['الرصيد الحالي حسب المتغير', 'اتجاه حركة المخزون', 'سرعة حركة المنتجات'],
        'statistics.materials' => ['مخزون الخامات الحالي', 'الاستهلاك والهالك', 'سرعة حركة الخامات'],
        'statistics.manufacturing' => ['المخطط مقابل المنتج', 'الإنتاج حسب العملية', 'مساءلة عمليات التصنيع'],
        'statistics.workers' => ['الإنتاج والعيوب حسب العامل', 'نسبة الهالك'],
        'statistics.defects' => ['أسباب العيوب', 'العيوب حسب العامل', 'انحراف الخامات'],
        'statistics.returns' => ['اتجاه المرتجعات', 'أكثر المنتجات إرجاعًا', 'العملاء الأعلى مرتجعات'],
    ];

    foreach ($pages as $routeName => $markers) {
        $this->actingAs($user)->get(route($routeName))->assertOk()->assertSee($markers);
    }
});

it('shows only relevant shared filters and applies them through child routes', function () {
    $fixture = statisticsFixture();
    $user = statisticsUser();

    $this->actingAs($user)->get(route('statistics.sales', ['product_id' => $fixture['productA']->id]))
        ->assertOk()
        ->assertSee('stats-product_id', false)
        ->assertDontSee('stats-worker_id', false)
        ->assertSee('100.00 ج.م');

    $this->actingAs($user)->get(route('statistics.sales', [
        'preset' => 'custom',
        'date_from' => now()->subYear()->startOfMonth()->toDateString(),
        'date_to' => now()->subYear()->endOfMonth()->toDateString(),
    ]))->assertOk()->assertSee('500.00 ج.م');
});

it('calculates confirmed sales and rankings while excluding cancelled invoices', function () {
    $fixture = statisticsFixture();
    $data = statisticsData();

    expect($data['sales']['total'])->toBe(350.0)
        ->and($data['sales']['count'])->toBe(2)
        ->and($data['sales']['average'])->toBe(175.0)
        ->and($data['sales']['quantity'])->toBe(10.0)
        ->and($data['sales']['top_products_quantity']->first()->id)->toBe($fixture['productB']->id)
        ->and($data['customers']['top_sales']->first()->id)->toBe($fixture['customerB']->id);
});

it('uses confirmed collections and authoritative customer ledger balances', function () {
    statisticsFixture();
    $data = statisticsData();

    expect($data['collections']['total'])->toBe(80.0)
        ->and($data['collections']['by_method']->pluck('value', 'payment_method')->map(fn ($value) => (float) $value)->all())->toMatchArray(['cash' => 30.0, 'bank_transfer' => 50.0])
        ->and($data['customers']['total_outstanding'])->toBe(270.0)
        ->and((float) $data['customers']['top_outstanding']->first()->outstanding)->toBe(200.0);
});

it('calculates confirmed sales returns and excludes cancelled returns', function () {
    $fixture = statisticsFixture();
    $data = statisticsData();

    expect($data['returns']['count'])->toBe(1)
        ->and($data['returns']['quantity'])->toBe(1.0)
        ->and($data['returns']['value'])->toBe(50.0)
        ->and($data['returns']['rate'])->toBe(10.0)
        ->and($data['returns']['top_products']->first()->id)->toBe($fixture['productB']->id);
});

it('calculates production totals and worker defect and waste rates', function () {
    $fixture = statisticsFixture();
    $data = statisticsData();
    $worker = $data['workers']['rows']->first();

    expect($data['production']['totals']['planned'])->toBe(10.0)
        ->and($data['production']['totals']['produced'])->toBe(8.0)
        ->and($data['production']['totals']['remaining'])->toBe(2.0)
        ->and($worker->id)->toBe($fixture['worker']->id)
        ->and($worker->produced)->toBe(8.0)
        ->and($worker->defect_rate)->toBe(12.5)
        ->and($worker->waste_rate)->toBe(6.25);
});

it('aggregates material accountability without duplicating inventory deduction', function () {
    $fixture = statisticsFixture();
    $before = MaterialStockMovement::count();
    $data = statisticsData();
    $row = $data['materials']['rows']->first();

    expect($row->required)->toBe(5.0)
        ->and($row->allocated)->toBe(5.0)
        ->and($row->issued)->toBe(5.0)
        ->and($row->used)->toBe(4.0)
        ->and($row->returned)->toBe(.5)
        ->and($row->waste)->toBe(.5)
        ->and(app(MaterialStockService::class)->getCurrentStock($fixture['material']))->toBe('5.000')
        ->and(MaterialStockMovement::count())->toBe($before);
});

it('ranks real defect reasons and operations', function () {
    statisticsFixture();
    $data = statisticsData();

    expect($data['defects']['count'])->toBe(1)
        ->and($data['defects']['quantity'])->toBe(1.0)
        ->and($data['defects']['by_reason']->first()->reason)->toBe('wrong_cutting')
        ->and($data['operations']['highest_defect_rate']->defect_rate)->toBe(12.5);
});

it('applies date product and worker filters consistently', function () {
    $fixture = statisticsFixture();
    $productData = statisticsData(['product_id' => $fixture['productA']->id]);
    $workerData = statisticsData(['worker_id' => $fixture['worker']->id]);
    $oldData = statisticsData(['preset' => 'custom', 'date_from' => now()->subYear()->startOfMonth()->toDateString(), 'date_to' => now()->subYear()->endOfMonth()->toDateString()]);

    expect($productData['sales']['total'])->toBe(100.0)
        ->and($productData['sales']['by_product'])->toHaveCount(1)
        ->and($workerData['workers']['rows'])->toHaveCount(1)
        ->and($oldData['sales']['total'])->toBe(500.0)
        ->and($oldData['production']['totals']['orders'])->toBe(0);
});

it('renders empty datasets and keeps statistics query count bounded', function () {
    $user = statisticsUser();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('statistics.sales', [
        'preset' => 'custom',
        'date_from' => '2040-01-01',
        'date_to' => '2040-01-31',
    ]));
    $queryCount = count(DB::getQueryLog());

    $response->assertOk()->assertSee('لا توجد مبيعات مؤكدة في الفترة.');
    expect($queryCount)->toBeLessThan(30);
});
