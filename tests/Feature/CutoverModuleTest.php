<?php

use App\Actions\Cutover\AddOpeningBalanceAction;
use App\Actions\Cutover\CreateCutoverBatchAction;
use App\Actions\WorkerWage\CreateWorkerWagePaymentAction;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CutoverBatch;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCostLayer;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use App\Services\Accounting\JournalEntryService;
use App\Services\Cutover\CutoverCsvImportService;
use App\Services\Cutover\CutoverPostingService;
use App\Services\Cutover\CutoverReconciliationService;
use App\Services\Cutover\CutoverReversalService;
use App\Services\InventoryCostingService;
use App\Services\StockService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(AccountingSeeder::class);
    $this->user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $this->actingAs($this->user);
    FiscalYear::query()->create([
        'name' => '2040/2041-'.Str::random(5),
        'start_date' => '2040-10-01',
        'end_date' => '2041-09-30',
        'status' => 'open',
    ]);
});

function cutoverFixture(): array
{
    $key = Str::upper(Str::random(6));
    $category = Category::query()->create(['code' => "CUT-CAT-{$key}", 'name' => "Cutover Category {$key}", 'status' => 'active']);
    $product = Product::query()->create(['category_id' => $category->id, 'name' => "Cutover Product {$key}", 'code' => "CUT-P-{$key}", 'base_cost_price' => 0, 'cost_price' => 0, 'status' => 'active']);
    $color = Color::query()->create(['name' => "Cutover Black {$key}", 'code' => "CB-{$key}", 'status' => 'active']);
    $size = Size::query()->create(['name' => "Cutover M {$key}", 'code' => "CM-{$key}", 'status' => 'active']);
    $variant = ProductVariant::query()->create(['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id, 'sku' => "CUT-SKU-{$key}", 'barcode' => "CUT-BAR-{$key}", 'default_price' => 500, 'quantity' => 0, 'status' => 'active']);
    $material = Material::query()->create(['code' => "CUT-MAT-{$key}", 'name' => "Cutover Material {$key}", 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'status' => 'active']);
    $worker = Worker::query()->create(['code' => "CUT-WRK-{$key}", 'name' => "Cutover Worker {$key}", 'status' => 'active']);
    $partner = Partner::query()->create(['code' => "CUT-PT-{$key}", 'name' => "Cutover Partner {$key}", 'profit_share' => 0, 'is_active' => true]);
    $bom = Bom::query()->create(['bom_number' => "CUT-BOM-{$key}", 'version' => 1, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'basis_quantity' => 1, 'status' => 'active']);
    $order = ProductionOrder::query()->create([
        'order_number' => "CUT-MO-{$key}", 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'bom_id' => $bom->id,
        'product_name' => $product->name, 'color_name' => $color->name, 'size_name' => $size->name, 'sku' => $variant->sku,
        'bom_number' => $bom->bom_number, 'bom_version' => 1, 'planned_quantity' => 100, 'planned_start_date' => '2040-10-01', 'status' => 'in_progress',
    ]);

    return compact('product', 'variant', 'material', 'worker', 'partner', 'order');
}

function completeOpeningBatch(array $fixture): CutoverBatch
{
    $batch = app(CreateCutoverBatchAction::class)->handle([
        'cutover_date' => '2040-10-01', 'reference' => 'GO-LIVE-'.Str::uuid(), 'description' => 'Verified opening state',
    ], auth()->id());
    $add = app(AddOpeningBalanceAction::class);
    $cash = AccountMapping::query()->where('key', 'cash')->value('account_id');
    $bank = AccountMapping::query()->where('key', 'bank')->value('account_id');
    $retained = AccountMapping::query()->where('key', 'retained_earnings')->value('account_id');
    $rows = [
        ['type' => 'cash_bank', 'account_id' => $cash, 'amount' => 50000, 'layer_reference' => 'CASH-OPEN'],
        ['type' => 'cash_bank', 'account_id' => $bank, 'amount' => 120000, 'layer_reference' => 'BANK-OPEN'],
        ['type' => 'material', 'material_id' => $fixture['material']->id, 'quantity' => 100, 'unit_cost' => 10, 'layer_reference' => 'MAT-L1'],
        ['type' => 'material', 'material_id' => $fixture['material']->id, 'quantity' => 100, 'unit_cost' => 12, 'layer_reference' => 'MAT-L2'],
        ['type' => 'finished_goods', 'product_variant_id' => $fixture['variant']->id, 'quantity' => 100, 'unit_cost' => 300, 'layer_reference' => 'FG-L1'],
        ['type' => 'finished_goods', 'product_variant_id' => $fixture['variant']->id, 'quantity' => 100, 'unit_cost' => 350, 'layer_reference' => 'FG-L2'],
        ['type' => 'wip', 'production_order_id' => $fixture['order']->id, 'amount' => 5500, 'layer_reference' => 'WIP-OPEN'],
        ['type' => 'worker_payable', 'worker_id' => $fixture['worker']->id, 'production_order_id' => $fixture['order']->id, 'amount' => 3000, 'layer_reference' => 'WAGE-OPEN'],
        ['type' => 'partner', 'partner_id' => $fixture['partner']->id, 'partner_balance_type' => 'current', 'amount' => 20000, 'layer_reference' => 'PARTNER-OPEN'],
        ['type' => 'other', 'account_id' => $retained, 'amount' => 219700, 'layer_reference' => 'EQUITY-OPEN'],
    ];
    foreach ($rows as $row) {
        $add->handle($batch, $row);
    }

    return app(CutoverPostingService::class)->post($batch, auth()->id());
}

it('creates an idempotent draft and twelve monthly fiscal periods', function () {
    $data = ['cutover_date' => '2040-10-01', 'reference' => 'GO-LIVE-2040', 'description' => 'Opening'];
    $first = app(CreateCutoverBatchAction::class)->handle($data, $this->user->id);
    $second = app(CreateCutoverBatchAction::class)->handle($data, $this->user->id);

    expect($first->id)->toBe($second->id)
        ->and($first->batch_number)->toStartWith('CUT-')
        ->and(FiscalYear::query()->whereDate('start_date', '2040-10-01')->firstOrFail()->periods()->where('name', 'like', '____-__')->count())->toBe(12);
});

it('posts all opening sections as one balanced journal and reports go-live readiness', function () {
    $fixture = cutoverFixture();
    $customer = Customer::query()->create(['code' => 'CUT-CUS-'.Str::random(5), 'name' => 'Existing Customer', 'phone' => '01000000009', 'credit_limit' => 0, 'status' => 'active']);
    $supplier = Supplier::query()->create(['code' => 'CUT-SUP-'.Str::random(5), 'name' => 'Existing Supplier', 'status' => 'active']);
    $customerCounts = [Customer::count(), Supplier::count(), CustomerLedgerEntry::count()];

    $batch = completeOpeningBatch($fixture);
    $report = app(CutoverReconciliationService::class)->report($batch);

    expect($batch->status)->toBe('posted')
        ->and($batch->journalEntry->lines->sum('debit'))->toEqual($batch->journalEntry->lines->sum('credit'))
        ->and($report['ready'])->toBeTrue()
        ->and($report['clearing'])->toBe('0.00')
        ->and($report['unvalued'])->toBe(0)
        ->and($batch->journalEntry->lines->whereNotNull('cutover_opening_balance_id')->count())->toBe($batch->balances->where('amount', '!=', '0.00')->count())
        ->and((string) $fixture['order']->fresh()->opening_wip_cost)->toBe('5500.00')
        ->and(ProductionWorkerWage::query()->where('worker_id', $fixture['worker']->id)->value('remaining_amount'))->toEqual(3000)
        ->and([Customer::count(), Supplier::count(), CustomerLedgerEntry::count()])->toBe($customerCounts)
        ->and($customer->fresh()->name)->toBe('Existing Customer')
        ->and($supplier->fresh()->name)->toBe('Existing Supplier');
});

it('preserves separate material and product fifo layers and consumes them in exact order', function () {
    $fixture = cutoverFixture();
    completeOpeningBatch($fixture);
    $materialLayers = MaterialCostLayer::query()->where('material_id', $fixture['material']->id)->orderBy('id')->get();
    $productLayers = ProductCostLayer::query()->where('product_variant_id', $fixture['variant']->id)->orderBy('id')->get();

    $materialMovement = MaterialStockMovement::query()->create(['material_id' => $fixture['material']->id, 'movement_type' => 'adjustment_out', 'direction' => 'out', 'quantity' => 150, 'movement_date' => '2040-10-02']);
    $materialCost = app(InventoryCostingService::class)->consumeMaterialMovement($materialMovement);
    $productMovement = StockMovement::query()->create(['product_variant_id' => $fixture['variant']->id, 'movement_type' => 'adjustment_out', 'direction' => 'out', 'quantity' => 120, 'movement_date' => '2040-10-02']);
    $productCost = app(InventoryCostingService::class)->consumeProductMovement($productMovement);

    expect($materialLayers)->toHaveCount(2)
        ->and((string) $materialLayers[0]->unit_cost)->toBe('10.0000')
        ->and((string) $materialLayers[1]->unit_cost)->toBe('12.0000')
        ->and($materialCost)->toBe('1600.00')
        ->and((string) $materialLayers[1]->fresh()->remaining_quantity)->toBe('50.000')
        ->and($productLayers)->toHaveCount(2)
        ->and($productCost)->toBe('37000.00')
        ->and((string) $productLayers[1]->fresh()->remaining_quantity)->toBe('80.000');
});

it('settles opening worker payable without creating a new expense', function () {
    $fixture = cutoverFixture();
    completeOpeningBatch($fixture);
    $expenseAccountIds = Account::query()->where('type', 'expense')->pluck('id');
    $beforeExpenseLines = JournalEntryLine::query()->whereIn('account_id', $expenseAccountIds)->count();

    app(CreateWorkerWagePaymentAction::class)->handle([
        'worker_id' => $fixture['worker']->id,
        'payment_date' => '2040-10-02',
        'payment_account_id' => AccountMapping::query()->where('key', 'cash')->value('account_id'),
        'amount' => 1000,
        'description' => 'Partial opening wage payment',
        'reference' => 'WAGE-PAY-1',
        'submission_token' => (string) Str::uuid(),
    ], $this->user->id);

    $wage = ProductionWorkerWage::query()->where('worker_id', $fixture['worker']->id)->firstOrFail();
    expect((string) $wage->remaining_amount)->toBe('2000.00')
        ->and(JournalEntryLine::query()->whereIn('account_id', $expenseAccountIds)->count())->toBe($beforeExpenseLines);
});

it('blocks duplicate posting and unsafe reversal after fifo consumption', function () {
    $fixture = cutoverFixture();
    $batch = completeOpeningBatch($fixture);
    $entryCount = JournalEntry::query()->count();
    app(CutoverPostingService::class)->post($batch, $this->user->id);
    expect(JournalEntry::query()->count())->toBe($entryCount);

    $movement = MaterialStockMovement::query()->create(['material_id' => $fixture['material']->id, 'movement_type' => 'adjustment_out', 'direction' => 'out', 'quantity' => 1, 'movement_date' => '2040-10-02']);
    app(InventoryCostingService::class)->consumeMaterialMovement($movement);
    app(CutoverReversalService::class)->reverse($batch, '2040-10-03', $this->user->id);
})->throws(ValidationException::class);

it('previews csv without saving and rejects customer and supplier accounts from general openings', function () {
    $batch = app(CreateCutoverBatchAction::class)->handle(['cutover_date' => '2040-10-01', 'reference' => 'CSV-'.Str::uuid()], $this->user->id);
    $material = Material::query()->create(['code' => 'CSV-MAT-'.Str::random(4), 'name' => 'CSV Material', 'category' => 'fabric', 'unit' => 'meter', 'minimum_stock' => 0, 'quantity' => 0, 'status' => 'active']);
    $csv = "material_code,quantity,unit_cost,reference,description\n{$material->code},100,10,CSV-L1,First\n{$material->code},50,,CSV-L2,Unknown cost\n";
    $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);
    $preview = app(CutoverCsvImportService::class)->preview($batch, 'material', $file);

    expect($preview['valid'])->toBe(2)
        ->and($preview['warnings'])->toHaveCount(1)
        ->and($batch->balances()->count())->toBe(0);
    app(CutoverCsvImportService::class)->commit($batch, $preview);
    expect($batch->balances()->count())->toBe(2);

    app(AddOpeningBalanceAction::class)->handle($batch, [
        'type' => 'other',
        'account_id' => AccountMapping::query()->where('key', 'accounts_receivable')->value('account_id'),
        'amount' => 100,
        'layer_reference' => 'FORBIDDEN-AR',
    ]);
})->throws(ValidationException::class);

it('reverses an unused batch consistently across gl fifo wip and wages', function () {
    $fixture = cutoverFixture();
    $batch = completeOpeningBatch($fixture);
    $reversed = app(CutoverReversalService::class)->reverse($batch, '2040-10-02', $this->user->id);

    expect($reversed->status)->toBe('reversed')
        ->and($reversed->reversalJournalEntry)->not->toBeNull()
        ->and((string) MaterialCostLayer::query()->where('material_id', $fixture['material']->id)->sum('remaining_quantity'))->toBe('0.000')
        ->and((string) ProductCostLayer::query()->where('product_variant_id', $fixture['variant']->id)->sum('remaining_quantity'))->toBe('0.000')
        ->and((string) $fixture['order']->fresh()->opening_wip_cost)->toBe('0.00')
        ->and(ProductionWorkerWage::query()->where('worker_id', $fixture['worker']->id)->firstOrFail()->status->value)->toBe('reversed')
        ->and(JournalEntryLine::query()->sum('debit'))->toEqual(JournalEntryLine::query()->sum('credit'));
});

it('keeps unvalued layers explicit and prevents go-live readiness', function () {
    $fixture = cutoverFixture();
    $batch = app(CreateCutoverBatchAction::class)->handle(['cutover_date' => '2040-10-01', 'reference' => 'UNVALUED-'.Str::uuid()], $this->user->id);
    $add = app(AddOpeningBalanceAction::class);
    $add->handle($batch, ['type' => 'material', 'material_id' => $fixture['material']->id, 'quantity' => 10, 'unit_cost' => null, 'layer_reference' => 'UNVALUED-MAT']);
    $add->handle($batch, ['type' => 'cash_bank', 'account_id' => AccountMapping::query()->where('key', 'cash')->value('account_id'), 'amount' => 100, 'layer_reference' => 'CASH-100']);
    $add->handle($batch, ['type' => 'other', 'account_id' => AccountMapping::query()->where('key', 'retained_earnings')->value('account_id'), 'amount' => 100, 'layer_reference' => 'EQUITY-100']);
    $batch = app(CutoverPostingService::class)->post($batch, $this->user->id);
    $report = app(CutoverReconciliationService::class)->report($batch);

    expect($report['unvalued'])->toBe(1)
        ->and($report['ready'])->toBeFalse()
        ->and(MaterialCostLayer::query()->where('material_id', $fixture['material']->id)->value('unit_cost'))->toBeNull();
});

it('carries partner current balance forward and repayment creates no expense', function () {
    $fixture = cutoverFixture();
    completeOpeningBatch($fixture);
    $current = AccountMapping::query()->where('key', 'partner_current')->value('account_id');
    $cash = AccountMapping::query()->where('key', 'cash')->value('account_id');
    $expenseAccounts = Account::query()->where('type', 'expense')->pluck('id');
    $expenseLines = JournalEntryLine::query()->whereIn('account_id', $expenseAccounts)->count();
    app(JournalEntryService::class)->post([
        'entry_date' => '2040-10-02', 'description' => 'Partner repayment', 'reference_type' => Partner::class,
        'reference_id' => $fixture['partner']->id, 'entry_type' => 'cutover_partner_repayment',
    ], [
        ['account_id' => $current, 'debit' => 5000, 'credit' => 0, 'partner_id' => $fixture['partner']->id],
        ['account_id' => $cash, 'debit' => 0, 'credit' => 5000],
    ]);
    $balance = JournalEntryLine::query()->where('partner_id', $fixture['partner']->id)->sum('credit') - JournalEntryLine::query()->where('partner_id', $fixture['partner']->id)->sum('debit');

    expect($balance)->toEqual(15000)
        ->and(JournalEntryLine::query()->whereIn('account_id', $expenseAccounts)->count())->toBe($expenseLines);
});

it('blocks newly posted accounting documents before the active cutover date', function () {
    $fixture = cutoverFixture();
    completeOpeningBatch($fixture);
    app(JournalEntryService::class)->post(['entry_date' => '2040-09-30', 'description' => 'Backdated entry'], [
        ['account_id' => AccountMapping::query()->where('key', 'cash')->value('account_id'), 'debit' => 10, 'credit' => 0],
        ['account_id' => AccountMapping::query()->where('key', 'partner_capital')->value('account_id'), 'debit' => 0, 'credit' => 10],
    ]);
})->throws(ValidationException::class);

it('retires the legacy opening-stock path after cutover posting', function () {
    $fixture = cutoverFixture();
    completeOpeningBatch($fixture);

    app(StockService::class)->createOpeningStock([]);
})->throws(ValidationException::class);

it('protects cutover routes with dedicated permissions', function () {
    $this->get(route('cutover.index'))->assertForbidden();
    $this->user->givePermissionTo('cutover.view');
    $this->get(route('cutover.index'))->assertOk()->assertSee('Cutover & Opening Balances', false);

    $batch = app(CreateCutoverBatchAction::class)->handle([
        'cutover_date' => '2040-10-01',
        'reference' => 'ROUTE-'.Str::uuid(),
    ], $this->user->id);
    $this->get(route('cutover.show', $batch))->assertOk()->assertSee($batch->batch_number);
});
