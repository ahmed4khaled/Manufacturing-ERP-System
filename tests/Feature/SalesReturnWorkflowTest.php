<?php

use App\Actions\Inventory\CreateOpeningStockAction;
use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesReturn\CancelSalesReturnAction;
use App\Actions\SalesReturn\ConfirmSalesReturnAction;
use App\Actions\SalesReturn\CreateSalesReturnAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CustomerLedgerService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

/** @return array{invoice: SalesInvoice, variant: ProductVariant} */
function salesReturnWorkflowContext(): array
{
    $category = Category::create([
        'code' => 'CAT-RETURN',
        'name' => 'اختبار المرتجعات',
        'status' => 'active',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'عباية مرتجع',
        'code' => 'PRD-RETURN',
        'cost_price' => 50,
        'status' => 'active',
    ]);
    $color = Color::create([
        'name' => 'أسود',
        'code' => 'BLK-RETURN',
        'status' => 'active',
    ]);
    $size = Size::create([
        'name' => 'كبير',
        'code' => 'L-RETURN',
        'status' => 'active',
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-RETURN',
        'barcode' => 'BAR-RETURN',
        'default_price' => 100,
        'quantity' => 0,
        'status' => 'active',
    ]);
    $openingMovement = app(CreateOpeningStockAction::class)->handle([
        'product_variant_id' => $variant->id,
        'quantity' => '20.000',
        'movement_date' => now()->toDateString(),
        'notes' => 'رصيد اختبار المرتجع',
    ]);
    ProductCostLayer::query()->where('stock_movement_id', $openingMovement->id)->update([
        'unit_cost' => '50.0000',
        'total_original_cost' => '1000.00',
    ]);

    $customer = Customer::create([
        'code' => 'CUS-RETURN',
        'name' => 'عميل المرتجعات',
        'phone' => '01000000004',
        'credit_limit' => 50000,
        'status' => 'active',
    ]);
    $invoice = SalesInvoice::create([
        'invoice_number' => 'INV-RETURN',
        'customer_id' => $customer->id,
        'invoice_date' => now()->toDateString(),
        'status' => SalesInvoiceStatus::DRAFT,
        'total_quantity' => '10.000',
        'subtotal' => '1000.00',
        'total_amount' => '1000.00',
    ]);
    $invoice->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'color_name' => $color->name,
        'size_name' => $size->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);

    return ['invoice' => $invoice->fresh('items'), 'variant' => $variant];
}

function salesReturnWorkflowData(SalesInvoice $invoice, string $quantity): array
{
    return [
        'sales_invoice_id' => $invoice->id,
        'return_date' => now()->toDateString(),
        'reason' => 'مقاس غير مناسب',
        'notes' => null,
        'items' => [[
            'sales_invoice_item_id' => $invoice->items->first()->id,
            'quantity' => $quantity,
        ]],
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a valid sales return as a draft without posting effects', function () {
    ['invoice' => $invoice, 'variant' => $variant] = salesReturnWorkflowContext();

    $salesReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '2.000'));

    expect($salesReturn->status)->toBe(SalesReturnStatus::DRAFT)
        ->and((string) $salesReturn->total_quantity)->toBe('2.000')
        ->and((string) $salesReturn->total_amount)->toBe('200.00')
        ->and(app(StockService::class)->getCurrentStock($variant))->toBe('10.000')
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::SALES_RETURN)
            ->where('reference_id', $salesReturn->id)
            ->exists())->toBeFalse();
});

it('confirms a sales return and posts inventory and customer effects atomically', function () {
    ['invoice' => $invoice, 'variant' => $variant] = salesReturnWorkflowContext();
    $salesReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '2.000'));

    app(ConfirmSalesReturnAction::class)->handle($salesReturn);

    expect($salesReturn->fresh()->status)->toBe(SalesReturnStatus::CONFIRMED)
        ->and(app(StockService::class)->getCurrentStock($variant))->toBe('12.000')
        ->and(app(CustomerLedgerService::class)->getBalance($invoice->customer_id))->toBe('800.00')
        ->and($invoice->fresh()->remaining_amount)->toBe(800.0);
});

it('rejects a sales return quantity above the sold quantity', function () {
    ['invoice' => $invoice] = salesReturnWorkflowContext();

    expect(fn () => app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '10.001')))
        ->toThrow(ValidationException::class);

    expect(SalesReturn::query()->count())->toBe(0);
});

it('rejects a return above the eligible quantity after a previous confirmed return', function () {
    ['invoice' => $invoice] = salesReturnWorkflowContext();
    $firstReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '3.000'));
    app(ConfirmSalesReturnAction::class)->handle($firstReturn);

    expect(fn () => app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '8.000')))
        ->toThrow(ValidationException::class);

    expect(SalesReturn::query()->count())->toBe(1);
});

it('does not duplicate effects when the same sales return is confirmed twice', function () {
    ['invoice' => $invoice, 'variant' => $variant] = salesReturnWorkflowContext();
    $salesReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '2.000'));

    $action = app(ConfirmSalesReturnAction::class);
    $action->handle($salesReturn);
    $action->handle($salesReturn);

    expect(StockMovement::query()
        ->where('movement_type', StockMovementType::SALES_RETURN)
        ->where('reference_id', $salesReturn->id)
        ->count())->toBe(1)
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::SALES_RETURN)
            ->where('reference_id', $salesReturn->id)
            ->count())->toBe(1)
        ->and(app(StockService::class)->getCurrentStock($variant))->toBe('12.000');
});

it('cancels a confirmed sales return and reverses its effects only once', function () {
    ['invoice' => $invoice, 'variant' => $variant] = salesReturnWorkflowContext();
    $salesReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '2.000'));
    app(ConfirmSalesReturnAction::class)->handle($salesReturn);

    $action = app(CancelSalesReturnAction::class);
    $action->handle($salesReturn);
    $action->handle($salesReturn);

    expect($salesReturn->fresh()->status)->toBe(SalesReturnStatus::CANCELLED)
        ->and(app(StockService::class)->getCurrentStock($variant))->toBe('10.000')
        ->and(app(CustomerLedgerService::class)->getBalance($invoice->customer_id))->toBe('1000.00')
        ->and(StockMovement::query()
            ->where('movement_type', StockMovementType::SALES_RETURN_REVERSAL)
            ->where('reference_id', $salesReturn->id)
            ->count())->toBe(1)
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::SALES_RETURN_REVERSAL)
            ->where('reference_id', $salesReturn->id)
            ->count())->toBe(1);
});

it('reduces the invoice payable amount used by payment receipt validation', function () {
    ['invoice' => $invoice] = salesReturnWorkflowContext();
    $salesReturn = app(CreateSalesReturnAction::class)
        ->handle(salesReturnWorkflowData($invoice, '2.000'));
    app(ConfirmSalesReturnAction::class)->handle($salesReturn);

    $paymentData = [
        'customer_id' => $invoice->customer_id,
        'sales_invoice_id' => $invoice->id,
        'payment_type' => 'invoice',
        'payment_date' => now()->toDateString(),
        'amount' => '800.00',
        'payment_method' => 'cash',
        'reference_number' => null,
        'notes' => null,
    ];
    $receipt = app(CreatePaymentReceiptAction::class)->handle($paymentData);
    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    expect($invoice->fresh()->remaining_amount)->toBe(0.0);

    expect(fn () => app(CreatePaymentReceiptAction::class)->handle([
        ...$paymentData,
        'amount' => '0.01',
    ]))->toThrow(ValidationException::class);
});
