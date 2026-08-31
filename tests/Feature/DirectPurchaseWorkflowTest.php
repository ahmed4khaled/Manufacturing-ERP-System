<?php

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreateGoodsReceiptAction;
use App\Actions\Procurement\CreateInvoiceGoodsReceiptAction;
use App\Actions\Procurement\CreatePurchaseReturnAction;
use App\Actions\Procurement\CreateSupplierInvoiceAction;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\MaterialCostAllocation;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\InventoryCostingService;
use App\Services\MaterialStockService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(AccountingSeeder::class);
});

function directPurchaseSupplier(string $suffix = 'A'): Supplier
{
    return Supplier::create(['code' => 'DP-SUP-'.$suffix, 'name' => 'Direct Supplier '.$suffix, 'status' => 'active']);
}

function directPurchaseMaterial(string $suffix = 'A'): Material
{
    return Material::create([
        'code' => 'DP-MAT-'.$suffix,
        'name' => 'Direct Material '.$suffix,
        'category' => 'fabric',
        'unit' => 'meter',
        'minimum_stock' => 0,
        'quantity' => 0,
        'status' => 'active',
    ]);
}

function directPurchaseData(Supplier $supplier, Material $material, string $quantity = '100.000', string $cost = '10.00', array $overrides = []): array
{
    return array_replace_recursive([
        'supplier_id' => $supplier->id,
        'supplier_reference' => 'SUP-'.Str::upper(Str::random(8)),
        'receipt_mode' => 'immediate',
        'submission_token' => (string) Str::uuid(),
        'invoice_date' => '2026-08-30',
        'discount' => '0.00',
        'tax' => '0.00',
        'items' => [[
            'material_id' => $material->id,
            'quantity' => $quantity,
            'unit_cost' => $cost,
        ]],
    ], $overrides);
}

it('creates one immediate receipt stock movement FIFO layer supplier liability and journal entry', function () {
    $supplier = directPurchaseSupplier();
    $material = directPurchaseMaterial();
    $invoice = app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material));
    $receipt = $invoice->goodsReceipt;
    $layer = MaterialCostLayer::where('goods_receipt_id', $receipt->id)->firstOrFail();
    $payableAccount = Account::where('subtype', 'accounts_payable')->firstOrFail();
    $payableBalance = JournalEntryLine::where('account_id', $payableAccount->id)->sum('credit')
        - JournalEntryLine::where('account_id', $payableAccount->id)->sum('debit');

    expect($invoice->items)->toHaveCount(1)
        ->and($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('confirmed')
        ->and($receipt->supplier_invoice_id)->toBe($invoice->id)
        ->and($receipt->purchase_order_id)->toBeNull()
        ->and((string) $receipt->items->first()->unit_cost)->toBe('10.00')
        ->and(app(MaterialStockService::class)->getCurrentStock($material))->toBe('100.000')
        ->and((string) $layer->original_quantity)->toBe('100.000')
        ->and((string) $layer->remaining_quantity)->toBe('100.000')
        ->and((string) $layer->unit_cost)->toBe('10.0000')
        ->and(SupplierInvoice::where('supplier_id', $supplier->id)->sum('total'))->toEqual(1000.0)
        ->and($payableBalance)->toEqual(1000.0)
        ->and(JournalEntry::where('reference_type', SupplierInvoice::class)->where('reference_id', $invoice->id)->count())->toBe(1);

    $this->get(route('suppliers.ledger', $supplier))->assertOk()->assertSee($invoice->invoice_number);
});

it('is idempotent for duplicate invoice submissions and repeated receipt requests', function () {
    $supplier = directPurchaseSupplier('IDEM');
    $material = directPurchaseMaterial('IDEM');
    $data = directPurchaseData($supplier, $material);
    $action = app(CreateSupplierInvoiceAction::class);

    $first = $action->handle($data);
    $second = $action->handle($data);
    $firstReceipt = app(CreateInvoiceGoodsReceiptAction::class)->handle($first);
    $secondReceipt = app(CreateInvoiceGoodsReceiptAction::class)->handle($first);

    expect($second->id)->toBe($first->id)
        ->and($secondReceipt->id)->toBe($firstReceipt->id)
        ->and(SupplierInvoice::where('submission_token', $data['submission_token'])->count())->toBe(1)
        ->and(GoodsReceipt::where('supplier_invoice_id', $first->id)->count())->toBe(1)
        ->and(MaterialStockMovement::where('reference_type', GoodsReceipt::class)->where('reference_id', $firstReceipt->id)->count())->toBe(1)
        ->and(MaterialCostLayer::where('goods_receipt_id', $firstReceipt->id)->count())->toBe(1)
        ->and(JournalEntry::where('reference_type', SupplierInvoice::class)->where('reference_id', $first->id)->count())->toBe(1);
});

it('keeps exact FIFO across two immediate purchases at different prices', function () {
    $supplier = directPurchaseSupplier('FIFO');
    $material = directPurchaseMaterial('FIFO');
    app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, '100.000', '10.00', ['invoice_date' => '2026-08-28']));
    app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, '100.000', '12.00', ['invoice_date' => '2026-08-29']));

    $issue = MaterialStockMovement::create([
        'material_id' => $material->id,
        'movement_type' => 'adjustment_out',
        'direction' => 'out',
        'quantity' => '150.000',
        'movement_date' => '2026-08-30',
    ]);
    $cost = app(InventoryCostingService::class)->consumeMaterialMovement($issue);
    $layers = MaterialCostLayer::where('material_id', $material->id)->orderBy('layer_date')->orderBy('id')->get();
    $allocations = MaterialCostAllocation::where('material_stock_movement_id', $issue->id)->orderBy('id')->get();

    expect($cost)->toBe('1600.00')
        ->and($allocations)->toHaveCount(2)
        ->and((string) $allocations[0]->quantity)->toBe('100.000')
        ->and((string) $allocations[0]->unit_cost)->toBe('10.0000')
        ->and((string) $allocations[1]->quantity)->toBe('50.000')
        ->and((string) $allocations[1]->unit_cost)->toBe('12.0000')
        ->and((string) $layers[0]->remaining_quantity)->toBe('0.000')
        ->and((string) $layers[1]->remaining_quantity)->toBe('50.000');
});

it('supports later receipt without duplicating the invoice accounting entry', function () {
    $supplier = directPurchaseSupplier('LATER');
    $material = directPurchaseMaterial('LATER');
    $invoice = app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, overrides: ['receipt_mode' => 'later']));

    expect($invoice->goods_receipt_id)->toBeNull()
        ->and(app(MaterialStockService::class)->getCurrentStock($material))->toBe('0.000')
        ->and(JournalEntry::where('reference_type', SupplierInvoice::class)->where('reference_id', $invoice->id)->count())->toBe(1);

    $receipt = app(CreateInvoiceGoodsReceiptAction::class)->handle($invoice);
    app(CreateInvoiceGoodsReceiptAction::class)->handle($invoice);

    expect($receipt->status)->toBe('confirmed')
        ->and(app(MaterialStockService::class)->getCurrentStock($material))->toBe('100.000')
        ->and(JournalEntry::where('reference_type', SupplierInvoice::class)->where('reference_id', $invoice->id)->count())->toBe(1);
});

it('keeps purchase returns tied to the direct receipt historical layer', function () {
    $supplier = directPurchaseSupplier('RETURN');
    $material = directPurchaseMaterial('RETURN');
    $invoice = app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material));
    $receipt = $invoice->goodsReceipt;
    $return = app(CreatePurchaseReturnAction::class)->handle([
        'supplier_id' => $supplier->id,
        'goods_receipt_id' => $receipt->id,
        'return_date' => '2026-08-31',
        'reason' => 'فحص مرتجع الشراء المباشر',
        'items' => [['material_id' => $material->id, 'quantity' => '20.000']],
    ]);
    app(ConfirmPurchaseReturnAction::class)->handle($return);

    expect($return->fresh()->status)->toBe('confirmed')
        ->and(app(MaterialStockService::class)->getCurrentStock($material))->toBe('80.000')
        ->and((string) MaterialCostLayer::where('goods_receipt_id', $receipt->id)->value('remaining_quantity'))->toBe('80.000')
        ->and((string) MaterialCostAllocation::where('reference_type', PurchaseReturn::class)->where('reference_id', $return->id)->sum('total_cost'))->toBe('200.00');
});

it('preserves the purchase order and manual goods receipt workflow', function () {
    $supplier = directPurchaseSupplier('PO');
    $material = directPurchaseMaterial('PO');
    $order = PurchaseOrder::create(['purchase_order_number' => 'DP-PO-1', 'supplier_id' => $supplier->id, 'order_date' => '2026-08-28', 'status' => 'approved', 'subtotal' => 500, 'discount' => 0, 'tax' => 0, 'total' => 500]);
    $orderItem = PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'material_id' => $material->id, 'quantity' => '50.000', 'unit_price' => '10.00', 'discount' => 0, 'tax' => 0, 'line_total' => 500]);
    $receipt = app(CreateGoodsReceiptAction::class)->handle([
        'purchase_order_id' => $order->id,
        'received_at' => '2026-08-29',
        'items' => [['purchase_order_item_id' => $orderItem->id, 'received_quantity' => '50.000', 'accepted_quantity' => '50.000', 'rejected_quantity' => '0.000', 'unit_cost' => '10.00']],
    ]);
    app(ConfirmGoodsReceiptAction::class)->handle($receipt);

    expect($receipt->fresh()->purchase_order_id)->toBe($order->id)
        ->and($receipt->fresh()->supplier_invoice_id)->toBeNull()
        ->and($order->fresh()->status)->toBe('fully_received')
        ->and(app(MaterialStockService::class)->getCurrentStock($material))->toBe('50.000');
});

it('rolls back invoice receipt stock FIFO and accounting when the fiscal period is closed', function () {
    $supplier = directPurchaseSupplier('CLOSED');
    $material = directPurchaseMaterial('CLOSED');
    $year = FiscalYear::create(['name' => 'Direct closed year', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    FiscalPeriod::create(['fiscal_year_id' => $year->id, 'name' => 'Direct closed period', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed']);

    expect(fn () => app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material)))
        ->toThrow(ValidationException::class);

    expect(SupplierInvoice::where('supplier_id', $supplier->id)->count())->toBe(0)
        ->and(GoodsReceipt::where('supplier_id', $supplier->id)->count())->toBe(0)
        ->and(MaterialStockMovement::where('material_id', $material->id)->count())->toBe(0)
        ->and(MaterialCostLayer::where('material_id', $material->id)->count())->toBe(0);
});

it('rejects invalid direct quantities costs and nonzero header adjustments', function () {
    $supplier = directPurchaseSupplier('VALID');
    $material = directPurchaseMaterial('VALID');

    expect(fn () => app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, '0.000')))->toThrow(ValidationException::class)
        ->and(fn () => app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, '1.000', '-1.00')))->toThrow(ValidationException::class)
        ->and(fn () => app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material, overrides: ['discount' => '1.00'])))->toThrow(ValidationException::class);

    expect(SupplierInvoice::where('supplier_id', $supplier->id)->count())->toBe(0);
});

it('handles duplicate HTTP submission tokens and request validation without duplicate effects', function () {
    $supplier = directPurchaseSupplier('HTTP');
    $material = directPurchaseMaterial('HTTP');
    $data = directPurchaseData($supplier, $material);

    $this->post(route('supplier-invoices.store'), $data)->assertRedirect();
    $this->post(route('supplier-invoices.store'), $data)->assertRedirect();

    expect(SupplierInvoice::where('submission_token', $data['submission_token'])->count())->toBe(1)
        ->and(GoodsReceipt::where('supplier_id', $supplier->id)->count())->toBe(1)
        ->and(MaterialStockMovement::where('material_id', $material->id)->count())->toBe(1)
        ->and(MaterialCostLayer::where('material_id', $material->id)->count())->toBe(1)
        ->and(JournalEntry::where('reference_type', SupplierInvoice::class)->count())->toBe(1);

    $invalid = directPurchaseData($supplier, $material, '-1.000');
    $this->post(route('supplier-invoices.store'), $invalid)
        ->assertSessionHasErrors('items.0.quantity');
});

it('leaves historical supplier invoices unchanged and renders the direct purchase UI', function () {
    $supplier = directPurchaseSupplier('HISTORY');
    $material = directPurchaseMaterial('HISTORY');
    $legacy = SupplierInvoice::create(['invoice_number' => 'LEGACY-DP', 'supplier_id' => $supplier->id, 'invoice_date' => '2026-01-01', 'subtotal' => 50, 'discount' => 0, 'tax' => 0, 'total' => 50, 'remaining_amount' => 50, 'status' => 'unpaid']);
    $snapshot = $legacy->only(['goods_receipt_id', 'receipt_mode', 'subtotal', 'total', 'remaining_amount']);

    app(CreateSupplierInvoiceAction::class)->handle(directPurchaseData($supplier, $material));

    expect($legacy->fresh()->only(array_keys($snapshot)))->toBe($snapshot)
        ->and(GoodsReceipt::where('supplier_invoice_id', $legacy->id)->count())->toBe(0);

    $this->get(route('supplier-invoices.create'))
        ->assertOk()
        ->assertSee('استلام فوري')
        ->assertSee('الاستلام لاحقًا')
        ->assertSee('سيتم إنشاء إذن استلام مؤكد')
        ->assertSee('name="submission_token"', false);
});
