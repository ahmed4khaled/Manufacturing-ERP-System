<?php

use App\Actions\Inventory\CreateOpeningStockAction;
use App\Actions\Inventory\CreateStockAdjustmentAction;
use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Enums\SalesInvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

function inventorySafetyVariant(): ProductVariant
{
    $category = Category::create([
        'code' => 'CAT-STOCK',
        'name' => 'اختبار المخزون',
        'status' => 'active',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'عباية اختبار',
        'code' => 'PRD-STOCK',
        'cost_price' => 50,
        'status' => 'active',
    ]);
    $color = Color::create([
        'name' => 'أسود',
        'code' => 'BLK-STOCK',
        'status' => 'active',
    ]);
    $size = Size::create([
        'name' => 'متوسط',
        'code' => 'M-STOCK',
        'status' => 'active',
    ]);

    return ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-STOCK',
        'barcode' => 'BAR-STOCK',
        'default_price' => 100,
        'quantity' => 0,
        'status' => 'active',
    ]);
}

function inventoryOpening(ProductVariant $variant, string $quantity): StockMovement
{
    return app(CreateOpeningStockAction::class)->handle([
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'movement_date' => now()->toDateString(),
        'notes' => 'رصيد اختبار',
    ]);
}

function inventorySalesInvoice(ProductVariant $variant, string $quantity): SalesInvoice
{
    $customer = Customer::create([
        'code' => 'CUS-STOCK',
        'name' => 'عميل اختبار المخزون',
        'phone' => '01000000003',
        'credit_limit' => 50000,
        'status' => 'active',
    ]);
    $total = bcmul($quantity, (string) $variant->default_price, 2);

    $invoice = SalesInvoice::create([
        'invoice_number' => 'INV-STOCK',
        'customer_id' => $customer->id,
        'invoice_date' => now()->toDateString(),
        'status' => SalesInvoiceStatus::DRAFT,
        'total_quantity' => $quantity,
        'subtotal' => $total,
        'total_amount' => $total,
    ]);

    $invoice->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->name,
        'color_name' => $variant->color->name,
        'size_name' => $variant->size->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode,
        'quantity' => $quantity,
        'unit_price' => $variant->default_price,
        'line_total' => $total,
    ]);

    return $invoice;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('issues stock and keeps the movement ledger and cached quantity consistent', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '10.000');

    app(CreateStockAdjustmentAction::class)->handle([
        'product_variant_id' => $variant->id,
        'direction' => 'out',
        'quantity' => '7.000',
        'movement_date' => now()->toDateString(),
        'notes' => 'صرف اختبار',
    ]);

    expect(app(StockService::class)->getCurrentStock($variant))->toBe('3.000')
        ->and((string) $variant->fresh()->quantity)->toBe('3.000');
});

it('rejects an issue above available stock without partial changes', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '10.000');

    expect(fn () => app(CreateStockAdjustmentAction::class)->handle([
        'product_variant_id' => $variant->id,
        'direction' => 'out',
        'quantity' => '11.000',
        'movement_date' => now()->toDateString(),
        'notes' => 'صرف زائد',
    ]))->toThrow(ValidationException::class);

    expect(app(StockService::class)->getCurrentStock($variant))->toBe('10.000')
        ->and(StockMovement::query()->count())->toBe(1)
        ->and((string) $variant->fresh()->quantity)->toBe('10.000');
});

it('stores calculates and displays decimal stock without truncation', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '125.750');

    expect(app(StockService::class)->getCurrentStock($variant))->toBe('125.750')
        ->and((string) $variant->fresh()->quantity)->toBe('125.750');

    $this->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('125.750');
});

it('confirms a sales invoice and deducts stock exactly once', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '10.000');
    $invoice = inventorySalesInvoice($variant, '3.250');

    $action = app(ConfirmSalesInvoiceAction::class);
    $action->handle($invoice);
    $action->handle($invoice);

    expect(app(StockService::class)->getCurrentStock($variant))->toBe('6.750')
        ->and((string) $variant->fresh()->quantity)->toBe('6.750')
        ->and(StockMovement::query()
            ->where('movement_type', StockMovementType::SALE)
            ->where('reference_id', $invoice->id)
            ->count())->toBe(1);
});

it('cancels a sales invoice and reverses its stock exactly once', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '10.000');
    $invoice = inventorySalesInvoice($variant, '3.250');
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);

    app(CancelSalesInvoiceAction::class)->handle($invoice);

    expect($invoice->fresh()->status)->toBe(SalesInvoiceStatus::CANCELLED)
        ->and(app(StockService::class)->getCurrentStock($variant))->toBe('10.000')
        ->and((string) $variant->fresh()->quantity)->toBe('10.000');
});

it('does not duplicate stock reversal when a sales cancellation is repeated', function () {
    $variant = inventorySafetyVariant();
    inventoryOpening($variant, '10.000');
    $invoice = inventorySalesInvoice($variant, '3.250');
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);

    $action = app(CancelSalesInvoiceAction::class);
    $action->handle($invoice);
    $action->handle($invoice);

    expect(app(StockService::class)->getCurrentStock($variant))->toBe('10.000')
        ->and(StockMovement::query()
            ->where('movement_type', StockMovementType::SALE_REVERSAL)
            ->where('reference_id', $invoice->id)
            ->count())->toBe(1);
});
