<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductVariant\CreateProductVariantAction;
use App\Actions\ProductVariant\UpdateProductVariantAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\VariantStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Worker;
use App\Services\BarcodeSvgService;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;

/** @return array<string, mixed> */
function barcodeFixture(string $suffix = 'A', ?string $barcode = '2000000000000'): array
{
    $category = Category::create(['code' => "BAR-CAT-{$suffix}", 'name' => "Barcode Category {$suffix}", 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'name' => "Barcode Abaya {$suffix}", 'code' => "BAR-P-{$suffix}", 'base_cost_price' => 10, 'cost_price' => 10, 'status' => 'active']);
    $color = Color::create(['name' => "Barcode Black {$suffix}", 'code' => 'C'.substr(md5($suffix), 0, 8), 'status' => 'active']);
    $size = Size::create(['name' => "Barcode Size {$suffix}", 'code' => "BAR-S-{$suffix}", 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => "BAR-SKU-{$suffix}", 'barcode' => $barcode, 'default_price' => 500, 'quantity' => 0, 'status' => 'active']);

    return compact('category', 'product', 'color', 'size', 'variant');
}

/** @return array<string, mixed> */
function barcodeProduction(string $suffix, string $barcode, string $good, string $defective): array
{
    $fixture = barcodeFixture($suffix, $barcode);
    $material = Material::create(['code' => "BAR-MAT-{$suffix}", 'name' => "Barcode Fabric {$suffix}", 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'standard_unit_cost' => 10, 'status' => 'active']);
    $operation = ManufacturingOperation::create(['name' => "Barcode Sewing {$suffix}", 'default_unit_cost' => 5, 'status' => 'active']);
    $worker = Worker::create(['code' => "BAR-W-{$suffix}", 'name' => "Barcode Worker {$suffix}", 'status' => 'active']);
    $bom = app(CreateBomAction::class)->handle(['product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'basis_quantity' => '1.000', 'items' => [['material_id' => $material->id, 'quantity' => '1.000']], 'operations' => [['manufacturing_operation_id' => $operation->id]]]);
    $bom = app(ActivateBomAction::class)->handle($bom);
    $order = app(CreateProductionOrderAction::class)->handle(['product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'bom_id' => $bom->id, 'planned_quantity' => '100.000', 'planned_start_date' => now()->toDateString(), 'assignments' => [['bom_operation_id' => $bom->operations->first()->id, 'worker_id' => $worker->id, 'assigned_quantity' => '100.000', 'materials' => [$bom->items->first()->id => ['allocated_quantity' => '100.000']]]]]);
    $order->workAssignments()->firstOrFail()->update(['produced_quantity' => bcadd($good, $defective, 3), 'good_quantity' => $good, 'defective_quantity' => $defective, 'status' => WorkAssignmentStatus::COMPLETED]);
    $order->update(['status' => ProductionOrderStatus::COMPLETED]);
    $output = $order->outputs()->create(['product_variant_id' => $fixture['variant']->id, 'quantity' => $good, 'output_date' => now()->toDateString(), 'recorded_by' => auth()->id(), 'posted_at' => now()]);

    return [...$fixture, 'order' => $order->fresh(), 'output' => $output];
}

function grantBarcodePermission(string $permission): void
{
    Permission::findOrCreate($permission, 'web');
    auth()->user()->givePermissionTo($permission);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('generates unique valid EAN-13 barcodes and preserves an existing barcode', function () {
    $fixture = barcodeFixture('GEN', '2999999999999');
    $sizeA = Size::create(['name' => 'Generated A', 'code' => 'GEN-A', 'status' => 'active']);
    $sizeB = Size::create(['name' => 'Generated B', 'code' => 'GEN-B', 'status' => 'active']);
    $first = app(CreateProductVariantAction::class)->handle(['product_id' => $fixture['product']->id, 'color_id' => $fixture['color']->id, 'size_id' => $sizeA->id, 'default_price' => 100]);
    $second = app(CreateProductVariantAction::class)->handle(['product_id' => $fixture['product']->id, 'color_id' => $fixture['color']->id, 'size_id' => $sizeB->id, 'default_price' => 100]);

    expect($first->barcode)->toMatch('/^2\d{12}$/')->not->toBe($second->barcode)
        ->and(app(BarcodeSvgService::class)->render($first->barcode))->toContain('aria-label="EAN-13');

    app(UpdateProductVariantAction::class)->handle($first, ['product_id' => $first->product_id, 'color_id' => $first->color_id, 'size_id' => $first->size_id, 'default_price' => 120, 'status' => VariantStatus::ACTIVE]);
    expect($first->fresh()->barcode)->toBe($first->barcode);
});

it('rejects a duplicate barcode at database level', function () {
    $first = barcodeFixture('DUP-A', '2111111111111');
    $second = barcodeFixture('DUP-B', '2111111111112');

    expect(fn () => $second['variant']->update(['barcode' => $first['variant']->barcode]))->toThrow(QueryException::class);
});

it('resolves a barcode clearly without creating stock or ledger effects', function () {
    $fixture = barcodeFixture('LOOKUP', '2222222222222');
    grantBarcodePermission('sales_invoices.create');
    $movementCount = StockMovement::count();
    $ledgerCount = CustomerLedgerEntry::count();

    $this->getJson(route('barcodes.lookup', ['context' => 'sales_invoice', 'barcode' => $fixture['variant']->barcode]))
        ->assertOk()->assertJsonPath('product_variant_id', $fixture['variant']->id)->assertJsonPath('sku', $fixture['variant']->sku);
    $this->getJson(route('barcodes.lookup', ['context' => 'sales_invoice', 'barcode' => 'INVALID']))
        ->assertUnprocessable()->assertJsonValidationErrors('barcode');

    expect(StockMovement::count())->toBe($movementCount)->and(CustomerLedgerEntry::count())->toBe($ledgerCount);
});

it('protects barcode lookup and label printing with existing permissions', function () {
    $fixture = barcodeFixture('AUTH', '2888888888888');

    $this->getJson(route('barcodes.lookup', ['context' => 'sales_invoice', 'barcode' => $fixture['variant']->barcode]))->assertForbidden();
    $this->get(route('product-variants.labels', ['productVariant' => $fixture['variant'], 'quantity' => 1]))->assertForbidden();
});

it('prints the requested product variant label quantity from the UI', function () {
    $fixture = barcodeFixture('LABEL', '2333333333333');
    grantBarcodePermission('product_variants.view');

    $this->get(route('product-variants.show', $fixture['variant']))->assertOk()->assertSee(route('product-variants.labels', $fixture['variant']));
    $single = $this->get(route('product-variants.labels', ['productVariant' => $fixture['variant'], 'quantity' => 1, 'mode' => 'preview']))
        ->assertOk()
        ->assertSee($fixture['product']->code)
        ->assertSee($fixture['color']->name)
        ->assertSee($fixture['size']->name)
        ->assertSee($fixture['variant']->sku)
        ->assertSee($fixture['variant']->barcode);
    $ten = $this->get(route('product-variants.labels', ['productVariant' => $fixture['variant'], 'quantity' => 10, 'mode' => 'preview']))->assertOk();
    $hundred = $this->get(route('product-variants.labels', ['productVariant' => $fixture['variant'], 'quantity' => 100, 'mode' => 'preview']))->assertOk();

    expect(substr_count($single->getContent(), '<section class="label"'))->toBe(1)
        ->and(substr_count($ten->getContent(), '<section class="label"'))->toBe(10)
        ->and(substr_count($hundred->getContent(), '<section class="label"'))->toBe(100);
});

it('defaults production labels to good output and excludes defective quantity', function () {
    $context = barcodeProduction('PROD', '2444444444444', '96.000', '4.000');
    grantBarcodePermission('production_orders.view');

    $response = $this->get(route('production-orders.labels', $context['order']))->assertOk();
    expect(substr_count($response->getContent(), '<section class="label"'))->toBe(96);

    $this->get(route('production-orders.show', $context['order']))->assertOk()->assertSee(route('production-orders.labels', $context['order']));

    $this->get(route('production-orders.labels', ['productionOrder' => $context['order'], 'quantity' => 97]))->assertSessionHasErrors('quantity');
});

it('prints a multi-variant production output batch without mixing labels', function () {
    $first = barcodeProduction('BATCH-A', '2555555555555', '2.000', '0.000');
    $second = barcodeProduction('BATCH-B', '2666666666666', '3.000', '0.000');
    grantBarcodePermission('production_orders.view');

    $this->get(route('production-labels.batch'))->assertOk()->assertSee($first['variant']->sku)->assertSee($second['variant']->sku);
    $response = $this->post(route('production-labels.print'), ['outputs' => [$first['output']->id => 2, $second['output']->id => 3], 'mode' => 'preview'])->assertOk();
    expect(substr_count($response->getContent(), 'data-sku="'.$first['variant']->sku.'"'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-sku="'.$second['variant']->sku.'"'))->toBe(3);
});

it('exposes operational scanning in sales returns and inventory without submitting transactions', function () {
    $fixture = barcodeFixture('UI', '2777777777777');
    $customer = Customer::create(['code' => 'BAR-CUS', 'name' => 'Barcode Customer', 'phone' => '01011111111', 'credit_limit' => 10000, 'status' => 'active']);
    $invoice = SalesInvoice::create(['invoice_number' => 'BAR-INV', 'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'status' => SalesInvoiceStatus::CONFIRMED, 'total_quantity' => 5, 'subtotal' => 2500, 'discount' => 0, 'total_amount' => 2500]);
    $invoice->items()->create(['product_variant_id' => $fixture['variant']->id, 'product_name' => $fixture['product']->name, 'color_name' => $fixture['color']->name, 'size_name' => $fixture['size']->name, 'sku' => $fixture['variant']->sku, 'barcode' => $fixture['variant']->barcode, 'quantity' => 5, 'unit_price' => 500, 'line_total' => 2500]);
    grantBarcodePermission('sales_invoices.create');
    grantBarcodePermission('inventory.adjustment');
    $movementCount = StockMovement::count();

    $this->get(route('sales-invoices.create'))
        ->assertOk()
        ->assertSee('barcodeScanInput', false)
        ->assertSee('barcodeScanQuantity', false)
        ->assertSee('variant.quantity = Number(variant.quantity) + scannedQuantity', false)
        ->assertSee('summary-variant-qty', false)
        ->assertDontSee('barcodeScanBtn', false);
    $this->get(route('sales-returns.create', ['sales_invoice_id' => $invoice->id]))->assertOk()->assertSee('returnBarcodeInput', false)->assertSee('data-product-variant-id="'.$fixture['variant']->id.'"', false);
    $this->get(route('inventory.adjustments.create'))->assertOk()->assertSee('product_variant_id_barcode', false);
    $this->get(route('inventory.opening-stock.create'))->assertOk()->assertSee('product_variant_id_barcode', false);
    $this->getJson(route('barcodes.lookup', ['context' => 'sales_return', 'barcode' => $fixture['variant']->barcode]))->assertOk()->assertJsonPath('product_variant_id', $fixture['variant']->id);
    $this->getJson(route('barcodes.lookup', ['context' => 'inventory', 'barcode' => $fixture['variant']->barcode]))->assertOk()->assertJsonPath('product_variant_id', $fixture['variant']->id);

    expect(StockMovement::count())->toBe($movementCount);
});
