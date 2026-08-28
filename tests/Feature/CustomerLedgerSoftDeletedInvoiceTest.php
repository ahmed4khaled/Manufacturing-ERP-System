<?php

use App\Actions\Inventory\CreateOpeningStockAction;
use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesInvoice\DeleteSalesInvoiceAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\User;
use App\Services\CustomerLedgerService;
use Illuminate\Support\Carbon;

/** @return array{customer: Customer, invoice: SalesInvoice} */
function softDeletedInvoiceLedgerFixture(): array
{
    $customer = Customer::create([
        'code' => 'CUS-LEDGER-SD',
        'name' => 'عميل اختبار كشف الحساب',
        'phone' => '01000000888',
        'credit_limit' => 100000,
        'status' => 'active',
    ]);
    $category = Category::create(['code' => 'CAT-LSD', 'name' => 'تصنيف اختبار', 'status' => 'active']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'عباية اختبار كشف الحساب',
        'code' => 'PRD-LSD',
        'cost_price' => 10000,
        'status' => 'active',
    ]);
    $color = Color::create(['name' => 'أسود', 'code' => 'BLSD', 'status' => 'active']);
    $size = Size::create(['name' => 'موحد', 'code' => 'OSD', 'status' => 'active']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-LEDGER-SD',
        'barcode' => 'BAR-LEDGER-SD',
        'default_price' => 27840,
        'quantity' => 0,
        'status' => 'active',
    ]);

    $openingMovement = app(CreateOpeningStockAction::class)->handle([
        'product_variant_id' => $variant->id,
        'quantity' => '1.000',
        'movement_date' => '2026-07-01',
        'notes' => 'رصيد اختبار',
    ]);
    ProductCostLayer::query()->where('stock_movement_id', $openingMovement->id)->update([
        'unit_cost' => '10000.0000',
        'total_original_cost' => '10000.00',
    ]);

    $invoice = SalesInvoice::create([
        'invoice_number' => 'INV000051',
        'customer_id' => $customer->id,
        'invoice_date' => '2026-07-31',
        'status' => SalesInvoiceStatus::DRAFT,
        'total_quantity' => '1.000',
        'subtotal' => '27840.00',
        'discount' => '0.00',
        'total_amount' => '27840.00',
    ]);
    $invoice->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'color_name' => $color->name,
        'size_name' => $size->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode,
        'quantity' => '1.000',
        'unit_price' => '27840.00',
        'line_total' => '27840.00',
    ]);

    return compact('customer', 'invoice');
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-27 12:00:00');
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

it('includes an active confirmed sales invoice in the active customer ledger and balance', function () {
    ['customer' => $customer, 'invoice' => $invoice] = softDeletedInvoiceLedgerFixture();

    app(ConfirmSalesInvoiceAction::class)->handle($invoice);

    expect(CustomerLedgerEntry::query()->activeBusiness()->count())->toBe(1)
        ->and(app(CustomerLedgerService::class)->getBalance($customer->id))->toBe('27840.00');
});

it('keeps both cancellation entries visible while the cancelled invoice is not deleted', function () {
    ['customer' => $customer, 'invoice' => $invoice] = softDeletedInvoiceLedgerFixture();
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);
    app(CancelSalesInvoiceAction::class)->handle($invoice);

    expect(CustomerLedgerEntry::query()->activeBusiness()->where('reference_id', $invoice->id)->count())->toBe(2)
        ->and(app(CustomerLedgerService::class)->getBalance($customer->id))->toBe('0.00');

    $this->get(route('customer-ledger.index'))->assertOk()->assertSee('INV000051');
    $this->get(route('customer-ledger.print', $customer))->assertOk()->assertSee('INV000051');
});

it('excludes the exact cancelled and soft-deleted INV000051 scenario without deleting audit history', function () {
    ['customer' => $customer, 'invoice' => $invoice] = softDeletedInvoiceLedgerFixture();

    $otherInvoice = SalesInvoice::create([
        'invoice_number' => 'INV000052',
        'customer_id' => $customer->id,
        'invoice_date' => '2026-07-01',
        'status' => SalesInvoiceStatus::CONFIRMED,
        'total_quantity' => '1.000',
        'subtotal' => '500.00',
        'discount' => '0.00',
        'total_amount' => '500.00',
    ]);
    CustomerLedgerEntry::create([
        'customer_id' => $customer->id,
        'reference_type' => LedgerReferenceType::SALES_INVOICE,
        'reference_id' => $otherInvoice->id,
        'reference_number' => $otherInvoice->invoice_number,
        'transaction_date' => '2026-07-01',
        'description' => 'فاتورة أخرى صحيحة',
        'debit' => '500.00',
        'credit' => '0.00',
    ]);

    $balanceBeforeErroneousInvoice = app(CustomerLedgerService::class)->getBalance($customer->id);
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);
    app(CancelSalesInvoiceAction::class)->handle($invoice);

    $historicalIds = CustomerLedgerEntry::query()
        ->where('reference_id', $invoice->id)
        ->whereIn('reference_type', [
            LedgerReferenceType::SALES_INVOICE,
            LedgerReferenceType::SALES_INVOICE_REVERSAL,
        ])
        ->pluck('id');

    app(DeleteSalesInvoiceAction::class)->handle($invoice);

    expect($balanceBeforeErroneousInvoice)->toBe('500.00')
        ->and(SalesInvoice::query()->find($invoice->id))->toBeNull()
        ->and(SalesInvoice::withTrashed()->find($invoice->id)?->trashed())->toBeTrue()
        ->and(CustomerLedgerEntry::query()->whereIn('id', $historicalIds)->count())->toBe(2)
        ->and(CustomerLedgerEntry::query()->whereIn('id', $historicalIds)->sum('debit'))->toEqual('27840.00')
        ->and(CustomerLedgerEntry::query()->whereIn('id', $historicalIds)->sum('credit'))->toEqual('27840.00')
        ->and(CustomerLedgerEntry::query()->activeBusiness()->whereIn('id', $historicalIds)->exists())->toBeFalse()
        ->and(CustomerLedgerEntry::query()->activeBusiness()->where('reference_id', $otherInvoice->id)->exists())->toBeTrue()
        ->and(app(CustomerLedgerService::class)->getBalance($customer->id))->toBe($balanceBeforeErroneousInvoice)
        ->and(app(CustomerLedgerService::class)->getBalances(collect([$customer->id]))->get($customer->id))->toBe('500.00')
        ->and(app(CustomerLedgerService::class)->getRecentEntries($customer->id)->pluck('reference_number')->all())->toBe(['INV000052'])
        ->and(CustomerLedgerEntry::query()->where('reference_type', LedgerReferenceType::SALES_INVOICE_REVERSAL)->where('reference_id', $invoice->id)->count())->toBe(1);

    $auditEntry = CustomerLedgerEntry::query()->with('salesInvoice')->findOrFail($historicalIds->first());
    expect($auditEntry->salesInvoice?->id)->toBe($invoice->id)
        ->and($auditEntry->salesInvoice?->trashed())->toBeTrue();

    $this->get(route('customer-ledger.index'))
        ->assertOk()
        ->assertDontSee('INV000051')
        ->assertSee('INV000052')
        ->assertSee('500.00');
    $this->get(route('customer-ledger.print', $customer))
        ->assertOk()
        ->assertDontSee('INV000051')
        ->assertSee('INV000052');
    $this->get(route('customers.show', $customer))
        ->assertOk()
        ->assertDontSee('INV000051')
        ->assertSee('INV000052');
    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertDontSee('INV000051')
        ->assertSee('INV000052');
});

it('calculates paginated running balances from active entries only', function () {
    ['customer' => $customer, 'invoice' => $invoice] = softDeletedInvoiceLedgerFixture();
    CustomerLedgerEntry::create([
        'customer_id' => $customer->id,
        'reference_type' => LedgerReferenceType::MANUAL_ADJUSTMENT,
        'reference_id' => 9001,
        'reference_number' => 'OPENING-500',
        'transaction_date' => '2026-07-01',
        'description' => 'رصيد سابق',
        'debit' => '500.00',
        'credit' => '0.00',
    ]);
    app(ConfirmSalesInvoiceAction::class)->handle($invoice);
    app(CancelSalesInvoiceAction::class)->handle($invoice);
    app(DeleteSalesInvoiceAction::class)->handle($invoice);

    $entries = CustomerLedgerEntry::query()
        ->activeBusiness()
        ->where('customer_id', $customer->id)
        ->orderBy('transaction_date')
        ->orderBy('id')
        ->paginate();
    app(CustomerLedgerService::class)->attachRunningBalances($entries);

    expect($entries->count())->toBe(1)
        ->and($entries->first()->reference_number)->toBe('OPENING-500')
        ->and($entries->first()->running_balance)->toBe('500.00');
});
