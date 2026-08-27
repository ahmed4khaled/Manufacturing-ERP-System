<?php

use App\Actions\SalesInvoice\CreateSalesInvoiceAction;
use App\Actions\SalesInvoice\UpdateSalesInvoiceAction;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** @return array{customer: Customer, product: Product, variant: ProductVariant} */
function salesInvoiceDiscountFixture(): array
{
    $customer = Customer::create([
        'code' => 'CUS-DISCOUNT',
        'name' => 'عميل الخصم',
        'phone' => '01000000999',
        'credit_limit' => 100000,
        'status' => 'active',
    ]);
    $category = Category::create([
        'code' => 'CAT-DISCOUNT',
        'name' => 'تصنيف الخصم',
        'status' => 'active',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'عباية الخصم',
        'code' => 'PRD-DISCOUNT',
        'cost_price' => 50,
        'status' => 'active',
    ]);
    $color = Color::create(['name' => 'أسود', 'code' => 'BLKD', 'status' => 'active']);
    $size = Size::create(['name' => 'وسط', 'code' => 'MD', 'status' => 'active']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-DISCOUNT',
        'barcode' => 'BAR-DISCOUNT',
        'default_price' => 250,
        'quantity' => 0,
        'status' => 'active',
    ]);

    return compact('customer', 'product', 'variant');
}

/** @param array{customer: Customer, product: Product, variant: ProductVariant} $fixture */
function salesInvoiceDiscountData(array $fixture, string $discount, string $quantity = '4'): array
{
    return [
        'customer_id' => $fixture['customer']->id,
        'invoice_date' => now()->toDateString(),
        'notes' => null,
        'discount' => $discount,
        'products' => [[
            'product_id' => $fixture['product']->id,
            'variants' => [[
                'product_variant_id' => $fixture['variant']->id,
                'quantity' => $quantity,
            ]],
        ]],
    ];
}

it('creates a sales invoice with a monetary discount and correct net total', function () {
    $fixture = salesInvoiceDiscountFixture();

    $invoice = app(CreateSalesInvoiceAction::class)
        ->handle(salesInvoiceDiscountData($fixture, '125.50'));

    expect((string) $invoice->subtotal)->toBe('1000.00')
        ->and((string) $invoice->discount)->toBe('125.50')
        ->and((string) $invoice->total_amount)->toBe('874.50');
});

it('recalculates the discounted total when a draft invoice is edited', function () {
    $fixture = salesInvoiceDiscountFixture();
    $invoice = app(CreateSalesInvoiceAction::class)
        ->handle(salesInvoiceDiscountData($fixture, '100.00'));

    $updated = app(UpdateSalesInvoiceAction::class)
        ->handle($invoice, salesInvoiceDiscountData($fixture, '50.00', '2'));

    expect((string) $updated->subtotal)->toBe('500.00')
        ->and((string) $updated->discount)->toBe('50.00')
        ->and((string) $updated->total_amount)->toBe('450.00');
});

it('rejects a discount greater than the server-calculated subtotal without partial data', function () {
    $fixture = salesInvoiceDiscountFixture();

    expect(fn () => app(CreateSalesInvoiceAction::class)
        ->handle(salesInvoiceDiscountData($fixture, '1000.01')))
        ->toThrow(ValidationException::class);

    expect(SalesInvoice::query()->count())->toBe(0);
});

it('shows the discount in invoice details and printing', function () {
    $this->actingAs(User::factory()->create());
    $fixture = salesInvoiceDiscountFixture();
    $invoice = app(CreateSalesInvoiceAction::class)
        ->handle(salesInvoiceDiscountData($fixture, '125.50'));

    $this->get(route('sales-invoices.show', $invoice))
        ->assertOk()
        ->assertSee('الخصم')
        ->assertSee('125.50');

    $this->get(route('sales-invoices.print', $invoice))
        ->assertOk()
        ->assertSee('الخصم')
        ->assertSee('125.50');
});
