<?php

use App\Actions\Bom\DeleteBomAction;
use App\Actions\Customer\DeleteCustomerAction;
use App\Actions\PaymentReceipt\DeletePaymentReceiptAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\ProductionOrder\DeleteProductionOrderAction;
use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesInvoice\DeleteSalesInvoiceAction;
use App\Actions\Supplier\DeleteSupplierAction;
use App\Actions\Worker\DeleteWorkerAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

function softDeletePolicyFixture(): array
{
    $category = Category::create(['code' => 'SD-CAT', 'name' => 'Soft Delete', 'status' => 'active']);
    $product = Product::create(['category_id' => $category->id, 'code' => 'SD-P', 'name' => 'Historical Product', 'cost_price' => 10, 'status' => 'active']);
    $color = Color::create(['code' => 'SD-BLK', 'name' => 'Black', 'status' => 'active']);
    $size = Size::create(['code' => 'SD-L', 'name' => 'Large', 'status' => 'active']);
    $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => 'SD-SKU', 'barcode' => 'SD-BAR', 'default_price' => 100, 'quantity' => 20, 'status' => 'active']);
    StockMovement::create(['product_variant_id' => $variant->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => 20, 'movement_date' => now()]);
    $customer = Customer::create(['code' => 'SD-C', 'name' => 'Historical Customer', 'phone' => '01000000010', 'credit_limit' => 10000, 'status' => 'active']);
    $invoice = SalesInvoice::create(['invoice_number' => 'SD-INV', 'customer_id' => $customer->id, 'invoice_date' => now(), 'status' => SalesInvoiceStatus::DRAFT, 'total_quantity' => 2, 'subtotal' => 200, 'total_amount' => 200]);
    $item = $invoice->items()->create(['product_variant_id' => $variant->id, 'product_name' => $product->name, 'color_name' => $color->name, 'size_name' => $size->name, 'sku' => $variant->sku, 'barcode' => $variant->barcode, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

    return compact('customer', 'invoice', 'item', 'product', 'variant');
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('soft deletes a draft invoice while retaining its lines', function () {
    $fixture = softDeletePolicyFixture();

    app(DeleteSalesInvoiceAction::class)->handle($fixture['invoice']);

    expect(SalesInvoice::find($fixture['invoice']->id))->toBeNull()
        ->and(SalesInvoice::withTrashed()->find($fixture['invoice']->id)->trashed())->toBeTrue()
        ->and($fixture['item']->fresh())->not->toBeNull();
});

it('requires reversal before a confirmed invoice can be soft deleted and retains history', function () {
    $fixture = softDeletePolicyFixture();
    app(ConfirmSalesInvoiceAction::class)->handle($fixture['invoice']);

    expect(fn () => app(DeleteSalesInvoiceAction::class)->handle($fixture['invoice']))
        ->toThrow(ValidationException::class);

    $movementCount = StockMovement::count();
    $ledgerCount = CustomerLedgerEntry::count();
    app(CancelSalesInvoiceAction::class)->handle($fixture['invoice']);
    app(DeleteSalesInvoiceAction::class)->handle($fixture['invoice']);

    expect(SalesInvoice::withTrashed()->find($fixture['invoice']->id)->trashed())->toBeTrue()
        ->and(StockMovement::count())->toBeGreaterThan($movementCount)
        ->and(CustomerLedgerEntry::count())->toBeGreaterThan($ledgerCount)
        ->and($fixture['item']->fresh())->not->toBeNull();
});

it('requires cancellation before a confirmed receipt can be deleted', function () {
    $fixture = softDeletePolicyFixture();
    $receipt = PaymentReceipt::create(['receipt_number' => 'SD-REC', 'customer_id' => $fixture['customer']->id, 'payment_date' => now(), 'amount' => 25, 'payment_method' => 'cash', 'status' => 'confirmed']);

    expect(fn () => app(DeletePaymentReceiptAction::class)->handle($receipt))
        ->toThrow(ValidationException::class);

    $receipt->update(['status' => 'cancelled']);
    app(DeletePaymentReceiptAction::class)->handle($receipt);

    expect(PaymentReceipt::withTrashed()->find($receipt->id)->trashed())->toBeTrue();
});

it('blocks customers with any non-zero balance and hides zero-balance customers', function () {
    $fixture = softDeletePolicyFixture();
    CustomerLedgerEntry::create(['customer_id' => $fixture['customer']->id, 'reference_type' => 'manual_adjustment', 'reference_id' => 1, 'transaction_date' => now(), 'description' => 'Balance', 'debit' => 10, 'credit' => 0]);

    expect(fn () => app(DeleteCustomerAction::class)->handle($fixture['customer']))
        ->toThrow(ValidationException::class, 'لا يمكن حذف العميل لأنه عليه رصيد مستحق.');

    CustomerLedgerEntry::create(['customer_id' => $fixture['customer']->id, 'reference_type' => 'manual_adjustment', 'reference_id' => 2, 'transaction_date' => now(), 'description' => 'Settlement', 'debit' => 0, 'credit' => 10]);
    app(DeleteCustomerAction::class)->handle($fixture['customer']);

    expect(Customer::find($fixture['customer']->id))->toBeNull()
        ->and(SalesInvoice::find($fixture['invoice']->id)->customer->id)->toBe($fixture['customer']->id)
        ->and(CustomerLedgerEntry::first()->customer->id)->toBe($fixture['customer']->id);

    $this->get(route('customers.index'))->assertDontSee('Historical Customer');
});

it('blocks suppliers with a balance and soft deletes settled suppliers', function () {
    $supplier = Supplier::create(['code' => 'SD-S', 'name' => 'Historical Supplier', 'status' => 'active']);
    $invoice = SupplierInvoice::create(['invoice_number' => 'SD-SINV', 'supplier_id' => $supplier->id, 'invoice_date' => now(), 'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'paid_amount' => 0, 'remaining_amount' => 100, 'status' => 'unpaid']);

    expect(fn () => app(DeleteSupplierAction::class)->handle($supplier))->toThrow(ValidationException::class);

    SupplierPayment::create(['supplier_id' => $supplier->id, 'supplier_invoice_id' => $invoice->id, 'payment_type' => 'invoice', 'payment_date' => now(), 'amount' => 100, 'payment_method' => 'cash']);
    app(DeleteSupplierAction::class)->handle($supplier);

    expect(Supplier::withTrashed()->find($supplier->id)->trashed())->toBeTrue()
        ->and($invoice->fresh()->supplier->id)->toBe($supplier->id);
});

it('retains historical worker assignments after soft deletion', function () {
    $fixture = softDeletePolicyFixture();
    $material = Material::create(['code' => 'SD-M', 'name' => 'Fabric', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 5, 'status' => 'active']);
    $operation = ManufacturingOperation::create(['name' => 'Cutting SD', 'default_unit_cost' => 1, 'status' => 'active']);
    $worker = Worker::create(['code' => 'SD-W', 'name' => 'Historical Worker', 'status' => 'active']);
    $bom = Bom::create(['bom_number' => 'SD-BOM', 'version' => 1, 'product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'basis_quantity' => 1, 'status' => 'active']);
    $order = ProductionOrder::create(['order_number' => 'SD-PO', 'product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'bom_id' => $bom->id, 'product_name' => $fixture['product']->name, 'sku' => $fixture['variant']->sku, 'bom_number' => $bom->bom_number, 'bom_version' => 1, 'planned_quantity' => 1, 'planned_start_date' => now(), 'status' => 'completed']);
    $orderOperation = ProductionOrderOperation::create(['production_order_id' => $order->id, 'manufacturing_operation_id' => $operation->id, 'operation_name' => $operation->name, 'unit_cost' => 1, 'total_cost' => 1, 'sequence' => 1]);
    $assignment = WorkAssignment::create(['production_order_id' => $order->id, 'production_order_operation_id' => $orderOperation->id, 'worker_id' => $worker->id, 'assigned_quantity' => 1, 'produced_quantity' => 1, 'good_quantity' => 1, 'defective_quantity' => 0, 'status' => 'completed']);

    app(DeleteWorkerAction::class)->handle($worker);

    expect(Worker::withTrashed()->find($worker->id)->trashed())->toBeTrue()
        ->and($assignment->fresh()->worker->id)->toBe($worker->id)
        ->and($material->exists)->toBeTrue();
});

it('preserves movement references after product and material soft deletion', function () {
    $fixture = softDeletePolicyFixture();
    $material = Material::create(['code' => 'SD-MOVE-M', 'name' => 'Historical Material', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 5, 'status' => 'active']);
    $stockMovement = StockMovement::create(['product_variant_id' => $fixture['variant']->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => 5, 'movement_date' => now()]);
    $materialMovement = MaterialStockMovement::create(['material_id' => $material->id, 'movement_type' => 'opening', 'direction' => 'in', 'quantity' => 5, 'movement_date' => now()]);

    app(DeleteProductAction::class)->handle($fixture['product']);
    $material->delete();

    expect(ProductVariant::withTrashed()->find($fixture['variant']->id)->trashed())->toBeTrue()
        ->and($stockMovement->fresh()->productVariant->id)->toBe($fixture['variant']->id)
        ->and($materialMovement->fresh()->material->id)->toBe($material->id);
});

it('restricts production order deletion to draft or cancelled states', function () {
    $fixture = softDeletePolicyFixture();
    $bom = Bom::create(['bom_number' => 'SD-PO-BOM', 'version' => 1, 'product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'basis_quantity' => 1, 'status' => 'active']);
    $order = ProductionOrder::create(['order_number' => 'SD-PO-DELETE', 'product_id' => $fixture['product']->id, 'product_variant_id' => $fixture['variant']->id, 'bom_id' => $bom->id, 'product_name' => $fixture['product']->name, 'sku' => $fixture['variant']->sku, 'bom_number' => $bom->bom_number, 'bom_version' => 1, 'planned_quantity' => 1, 'planned_start_date' => now(), 'status' => ProductionOrderStatus::CONFIRMED]);

    expect(fn () => app(DeleteBomAction::class)->handle($bom))->toThrow(ValidationException::class);
    expect(fn () => app(DeleteProductionOrderAction::class)->handle($order))->toThrow(ValidationException::class);

    $order->update(['status' => ProductionOrderStatus::CANCELLED]);
    app(DeleteProductionOrderAction::class)->handle($order);
    expect(ProductionOrder::withTrashed()->find($order->id)->trashed())->toBeTrue();
});

it('keeps ledgers and movements append-only and exposes no delete routes for them', function () {
    expect(class_uses_recursive(CustomerLedgerEntry::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(StockMovement::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(MaterialStockMovement::class))->not->toContain(SoftDeletes::class)
        ->and(Route::has('stock-movements.destroy'))->toBeFalse()
        ->and(Route::has('material-stock-movements.destroy'))->toBeFalse()
        ->and(Route::has('production-material-transactions.destroy'))->toBeFalse();
});
