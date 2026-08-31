<?php

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\Bom\UpdateBomAction;
use App\Actions\MaterialInventory\CreateMaterialOpeningStockAction;
use App\Actions\ProductionMaterial\IssueProductionMaterialsAction;
use App\Actions\ProductionMaterial\RecordMaterialUsageAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\WorkAssignment\CompleteWorkAssignmentAction;
use App\Actions\WorkAssignment\StartWorkAssignmentAction;
use App\Actions\WorkerWage\CreateWorkerWagePaymentAction;
use App\Actions\WorkerWage\GenerateProductionWorkerWagesAction;
use App\Actions\WorkerWage\ReverseWorkerWagePaymentAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionWorkerWageStatus;
use App\Enums\WorkAssignmentStatus;
use App\Enums\WorkerWagePaymentStatus;
use App\Models\AccountMapping;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Color;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\MaterialCostLayer;
use App\Models\Product;
use App\Models\ProductCostLayer;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerWagePayment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\DocumentPostingService;
use App\Services\Accounting\QuickExpenseAccountService;
use Carbon\Carbon;
use Database\Seeders\AccountingSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2035-08-31 10:00:00');
    $this->seed(AccountingSeeder::class);
    $this->wageUser = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $this->actingAs($this->wageUser);
});

afterEach(function () {
    Carbon::setTestNow();
});

function workerWageFixture(float $unitRate = 10): array
{
    $key = Str::upper(Str::random(5));
    $category = Category::query()->create(['code' => "WWC-{$key}", 'name' => "Wage category {$key}", 'status' => 'active']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'name' => "Wage product {$key}",
        'code' => "WWP-{$key}",
        'base_cost_price' => 100,
        'cost_price' => 100,
        'status' => 'active',
    ]);
    $color = Color::query()->create(['name' => "Wage color {$key}", 'code' => "WW-{$key}", 'status' => 'active']);
    $size = Size::query()->create(['name' => "Wage size {$key}", 'code' => "WW-{$key}", 'status' => 'active']);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => "WW-SKU-{$key}",
        'barcode' => "WW-BAR-{$key}",
        'default_price' => 0,
        'quantity' => 0,
        'status' => 'active',
    ]);
    $material = Material::query()->create([
        'code' => "WWM-{$key}",
        'name' => "Wage material {$key}",
        'category' => 'fabric',
        'unit' => 'meter',
        'minimum_stock' => 0,
        'quantity' => 0,
        'status' => 'active',
    ]);
    $operation = ManufacturingOperation::query()->create([
        'name' => "Wage operation {$key}",
        'default_unit_cost' => $unitRate,
        'status' => 'active',
    ]);
    $worker = Worker::query()->create(['code' => "WWW-{$key}", 'name' => "Wage worker {$key}", 'status' => 'active']);

    $bom = app(CreateBomAction::class)->handle([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'basis_quantity' => '1.000',
        'items' => [['material_id' => $material->id, 'quantity' => '3.000']],
        'operations' => [['manufacturing_operation_id' => $operation->id]],
    ]);
    $bom = app(ActivateBomAction::class)->handle($bom)->fresh(['items', 'operations']);

    return compact('product', 'variant', 'material', 'operation', 'worker', 'bom');
}

function workerWageOrder(array $fixture, string $quantity = '10.000'): ProductionOrder
{
    /** @var Bom $bom */
    $bom = $fixture['bom'];
    $allocated = bcmul($quantity, '3', 3);

    return app(CreateProductionOrderAction::class)->handle([
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'bom_id' => $bom->id,
        'planned_quantity' => $quantity,
        'planned_start_date' => now()->toDateString(),
        'assignments' => [[
            'bom_operation_id' => $bom->operations->first()->id,
            'worker_id' => $fixture['worker']->id,
            'assigned_quantity' => $quantity,
            'materials' => [$bom->items->first()->id => ['allocated_quantity' => $allocated]],
        ]],
    ]);
}

function earnedWorkerWage(array $fixture, string $quantity, string $earnedAt): ProductionWorkerWage
{
    $order = workerWageOrder($fixture, $quantity);
    $order->workAssignments()->update([
        'status' => WorkAssignmentStatus::COMPLETED,
        'produced_quantity' => $quantity,
        'good_quantity' => $quantity,
        'completed_at' => $earnedAt,
    ]);
    $order->update([
        'status' => ProductionOrderStatus::COMPLETED,
        'actual_operation_cost' => bcmul((string) $order->operations->first()->unit_cost, $quantity, 2),
        'closed_at' => $earnedAt,
    ]);

    return app(GenerateProductionWorkerWagesAction::class)->handle($order)->first();
}

function workerWagePaymentData(Worker $worker, array $overrides = []): array
{
    return array_replace([
        'worker_id' => $worker->id,
        'payment_date' => '2035-09-01',
        'payment_account_id' => AccountMapping::query()->where('key', 'cash')->value('account_id'),
        'amount' => '70.00',
        'description' => 'Production wage settlement',
        'reference' => 'WW-SETTLEMENT',
        'submission_token' => (string) Str::uuid(),
    ], $overrides);
}

it('creates one immutable wage from the production snapshot and remains idempotent', function () {
    $fixture = workerWageFixture(10);
    $order = workerWageOrder($fixture);
    $snapshotRate = (string) $order->operations->first()->unit_cost;

    $fixture['operation']->update(['default_unit_cost' => 99]);
    $fixture['bom']->operations->first()->update(['unit_cost' => 77]);
    $order->workAssignments()->update(['status' => WorkAssignmentStatus::COMPLETED]);
    $order->update(['status' => ProductionOrderStatus::COMPLETED, 'actual_operation_cost' => '100.00', 'closed_at' => '2035-08-31']);

    $first = app(GenerateProductionWorkerWagesAction::class)->handle($order);
    $second = app(GenerateProductionWorkerWagesAction::class)->handle($order);
    $wage = $first->first()->fresh();

    expect($snapshotRate)->toBe('10.00')
        ->and($second->first()->id)->toBe($wage->id)
        ->and(ProductionWorkerWage::where('production_order_id', $order->id)->count())->toBe(1)
        ->and((string) $wage->quantity)->toBe('10.000')
        ->and((string) $wage->unit_rate)->toBe('10.0000')
        ->and((string) $wage->gross_amount)->toBe('100.00')
        ->and((string) $wage->remaining_amount)->toBe('100.00')
        ->and($wage->status)->toBe(ProductionWorkerWageStatus::UNPAID)
        ->and(fn () => $wage->update(['gross_amount' => 1]))->toThrow(ValidationException::class);
});

it('generates the wage while completing production and preserves FIFO costing totals', function () {
    $fixture = workerWageFixture(10);
    app(CreateMaterialOpeningStockAction::class)->handle([
        'material_id' => $fixture['material']->id,
        'quantity' => '100.000',
        'movement_date' => now()->toDateString(),
    ]);
    MaterialCostLayer::query()->where('material_id', $fixture['material']->id)->update([
        'unit_cost' => '5.0000',
        'total_original_cost' => '500.00',
    ]);
    $order = workerWageOrder($fixture);
    app(ConfirmProductionOrderAction::class)->handle($order);
    $assignment = $order->fresh()->workAssignments()->first();
    $allocation = $assignment->materials()->first();
    app(IssueProductionMaterialsAction::class)->handle($allocation, ['quantity' => '30.000', 'occurred_at' => now()]);
    app(StartWorkAssignmentAction::class)->handle($assignment);
    app(RecordMaterialUsageAction::class)->handle($allocation, ['quantity' => '30.000', 'occurred_at' => now()]);
    app(CompleteWorkAssignmentAction::class)->handle($assignment, [
        'produced_quantity' => '10.000', 'good_quantity' => '10.000', 'defective_quantity' => '0.000',
    ]);
    app(RecordProductionOutputAction::class)->handle($order, ['quantity' => '10.000', 'output_date' => now()->toDateString()]);

    $completed = app(CompleteProductionOrderAction::class)->handle($order);
    app(CompleteProductionOrderAction::class)->handle($completed);
    $wage = $completed->workerWages()->firstOrFail();
    $entry = JournalEntry::query()->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)->where('entry_type', 'production_completion')->firstOrFail();
    $payable = AccountMapping::query()->where('key', 'production_wages_payable')->value('account_id');

    expect((string) $completed->fresh()->actual_material_cost)->toBe('150.00')
        ->and((string) $completed->fresh()->actual_operation_cost)->toBe('100.00')
        ->and((string) $completed->fresh()->final_total_cost)->toBe('250.00')
        ->and((string) $wage->gross_amount)->toBe('100.00')
        ->and($completed->workerWages()->count())->toBe(1)
        ->and($entry->lines()->where('account_id', $payable)->where('worker_id', $fixture['worker']->id)->sum('credit'))->toEqual('100.00')
        ->and(ProductCostLayer::query()->where('production_order_id', $order->id)->exists())->toBeTrue();
});

it('handles two production workers end to end and reconciles wages payable after full and partial settlements', function () {
    $fixture = workerWageFixture(10);
    $secondOperation = ManufacturingOperation::query()->create([
        'name' => 'Sewing wage '.Str::random(5),
        'default_unit_cost' => 20,
        'status' => 'active',
    ]);
    $secondWorker = Worker::query()->create([
        'code' => 'WWW-'.Str::upper(Str::random(5)),
        'name' => 'Ahmed wage worker',
        'status' => 'active',
    ]);
    $bom = app(UpdateBomAction::class)->handle($fixture['bom'], [
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'basis_quantity' => '1.000',
        'items' => [['material_id' => $fixture['material']->id, 'quantity' => '3.000']],
        'operations' => [
            ['manufacturing_operation_id' => $fixture['operation']->id],
            ['manufacturing_operation_id' => $secondOperation->id],
        ],
    ]);
    $bom = app(ActivateBomAction::class)->handle($bom)->fresh(['items', 'operations']);
    $order = app(CreateProductionOrderAction::class)->handle([
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'bom_id' => $bom->id,
        'planned_quantity' => '100.000',
        'planned_start_date' => now()->toDateString(),
        'assignments' => [
            [
                'bom_operation_id' => $bom->operations[0]->id,
                'worker_id' => $fixture['worker']->id,
                'assigned_quantity' => '100.000',
                'materials' => [$bom->items->first()->id => ['allocated_quantity' => '300.000']],
            ],
            [
                'bom_operation_id' => $bom->operations[1]->id,
                'worker_id' => $secondWorker->id,
                'assigned_quantity' => '100.000',
                'materials' => [],
            ],
        ],
    ]);
    $order->workAssignments()->update(['status' => WorkAssignmentStatus::COMPLETED, 'completed_at' => now()]);
    $order->update([
        'status' => ProductionOrderStatus::COMPLETED,
        'actual_material_cost' => '0.00',
        'actual_operation_cost' => '3000.00',
        'manufacturing_overhead_cost' => '0.00',
        'final_unit_cost' => '30.0000',
        'final_total_cost' => '3000.00',
        'closed_at' => now(),
    ]);
    $wages = app(GenerateProductionWorkerWagesAction::class)->handle($order)->keyBy('worker_id');
    app(DocumentPostingService::class)->production($order->fresh());

    expect((string) $wages[$fixture['worker']->id]->gross_amount)->toBe('1000.00')
        ->and((string) $wages[$secondWorker->id]->gross_amount)->toBe('2000.00')
        ->and((string) $wages->sum('gross_amount'))->toBe('3000')
        ->and((string) $order->fresh()->actual_operation_cost)->toBe('3000.00');

    $expenseCount = Expense::count();
    $pnlBefore = app(AccountingReportService::class)->profitAndLoss('2035-08-31', '2035-09-02');
    app(CreateWorkerWagePaymentAction::class)->handle(workerWagePaymentData($fixture['worker'], [
        'amount' => '500.00',
        'submission_token' => (string) Str::uuid(),
    ]), $this->wageUser->id);
    expect($wages[$fixture['worker']->id]->fresh()->status)->toBe(ProductionWorkerWageStatus::PARTIALLY_PAID)
        ->and((string) $wages[$fixture['worker']->id]->fresh()->remaining_amount)->toBe('500.00');

    app(CreateWorkerWagePaymentAction::class)->handle(workerWagePaymentData($fixture['worker'], [
        'amount' => '500.00',
        'submission_token' => (string) Str::uuid(),
    ]), $this->wageUser->id);
    $salmaWage = $wages[$fixture['worker']->id]->fresh();
    $pnlAfter = app(AccountingReportService::class)->profitAndLoss('2035-08-31', '2035-09-02');
    $payable = AccountMapping::query()->where('key', 'production_wages_payable')->value('account_id');
    $workerIds = [$fixture['worker']->id, $secondWorker->id];
    $payableBalance = JournalEntryLine::query()->where('account_id', $payable)->whereIn('worker_id', $workerIds)
        ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS balance')->value('balance');
    $outstanding = ProductionWorkerWage::query()->whereIn('worker_id', $workerIds)->sum('remaining_amount');

    expect($salmaWage->status)->toBe(ProductionWorkerWageStatus::PAID)
        ->and((string) $salmaWage->paid_amount)->toBe('1000.00')
        ->and((string) $salmaWage->remaining_amount)->toBe('0.00')
        ->and((float) $payableBalance)->toBe((float) $outstanding)
        ->and((float) $outstanding)->toBe(2000.0)
        ->and(collect($pnlAfter)->except(['revenue', 'expenses'])->all())->toBe(collect($pnlBefore)->except(['revenue', 'expenses'])->all())
        ->and(Expense::count())->toBe($expenseCount);
});

it('allocates partial payments FIFO and posts settlement without a second expense', function () {
    $fixture = workerWageFixture(10);
    $wages = collect([
        earnedWorkerWage($fixture, '4.000', '2035-08-01'),
        earnedWorkerWage($fixture, '6.000', '2035-08-10'),
        earnedWorkerWage($fixture, '8.000', '2035-08-20'),
    ]);
    $before = [
        'expenses' => Expense::count(),
        'customers' => CustomerLedgerEntry::count(),
        'supplier_invoices' => SupplierInvoice::count(),
        'supplier_payments' => SupplierPayment::count(),
        'material_layers' => MaterialCostLayer::count(),
        'product_layers' => ProductCostLayer::count(),
    ];

    $payment = app(CreateWorkerWagePaymentAction::class)->handle(workerWagePaymentData($fixture['worker']), $this->wageUser->id);
    $allocations = $payment->allocations()->orderBy('id')->get();
    $payable = AccountMapping::query()->where('key', 'production_wages_payable')->value('account_id');
    $cash = AccountMapping::query()->where('key', 'cash')->value('account_id');

    expect($payment->payment_number)->toMatch('/^WP-\d{6}$/')
        ->and($payment->status)->toBe(WorkerWagePaymentStatus::POSTED)
        ->and($allocations->pluck('production_worker_wage_id')->all())->toBe([$wages[0]->id, $wages[1]->id])
        ->and($allocations->pluck('amount')->all())->toBe(['40.00', '30.00'])
        ->and($wages[0]->fresh()->status)->toBe(ProductionWorkerWageStatus::PAID)
        ->and($wages[1]->fresh()->status)->toBe(ProductionWorkerWageStatus::PARTIALLY_PAID)
        ->and((string) $wages[1]->fresh()->remaining_amount)->toBe('30.00')
        ->and($wages[2]->fresh()->status)->toBe(ProductionWorkerWageStatus::UNPAID)
        ->and($payment->journalEntry->lines->firstWhere('account_id', $payable)->debit)->toBe('70.00')
        ->and($payment->journalEntry->lines->firstWhere('account_id', $payable)->worker_id)->toBe($fixture['worker']->id)
        ->and($payment->journalEntry->lines->firstWhere('account_id', $cash)->credit)->toBe('70.00');

    expect(Expense::count())->toBe($before['expenses'])
        ->and(CustomerLedgerEntry::count())->toBe($before['customers'])
        ->and(SupplierInvoice::count())->toBe($before['supplier_invoices'])
        ->and(SupplierPayment::count())->toBe($before['supplier_payments'])
        ->and(MaterialCostLayer::count())->toBe($before['material_layers'])
        ->and(ProductCostLayer::count())->toBe($before['product_layers']);
});

it('is idempotent, rejects overpayment, and rolls back a closed-period payment', function () {
    $fixture = workerWageFixture(10);
    $wage = earnedWorkerWage($fixture, '10.000', '2035-08-01');
    $data = workerWagePaymentData($fixture['worker'], ['amount' => '40.00']);
    $paymentCount = WorkerWagePayment::count();
    $journalCount = JournalEntry::count();

    $first = app(CreateWorkerWagePaymentAction::class)->handle($data, $this->wageUser->id);
    $second = app(CreateWorkerWagePaymentAction::class)->handle($data, $this->wageUser->id);
    expect($second->id)->toBe($first->id)
        ->and(WorkerWagePayment::count())->toBe($paymentCount + 1)
        ->and(JournalEntry::count())->toBe($journalCount + 1)
        ->and(fn () => app(CreateWorkerWagePaymentAction::class)->handle(
            workerWagePaymentData($fixture['worker'], ['amount' => '61.00']),
            $this->wageUser->id,
        ))->toThrow(ValidationException::class);

    $year = FiscalYear::query()->create([
        'name' => 'Closed worker wages '.Str::random(8),
        'start_date' => '2036-01-01',
        'end_date' => '2036-12-31',
        'status' => 'open',
    ]);
    FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'name' => 'Closed period',
        'start_date' => '2036-01-01',
        'end_date' => '2036-12-31',
        'status' => 'closed',
    ]);
    $token = (string) Str::uuid();
    $remainingBefore = (string) $wage->fresh()->remaining_amount;

    expect(fn () => app(CreateWorkerWagePaymentAction::class)->handle(workerWagePaymentData($fixture['worker'], [
        'payment_date' => '2036-04-01', 'amount' => '10.00', 'submission_token' => $token,
    ]), $this->wageUser->id))->toThrow(ValidationException::class)
        ->and(WorkerWagePayment::query()->where('submission_token', $token)->exists())->toBeFalse()
        ->and((string) $wage->fresh()->remaining_amount)->toBe($remainingBefore);
});

it('reverses once through the journal workflow and reopens FIFO balances', function () {
    $fixture = workerWageFixture(10);
    $firstWage = earnedWorkerWage($fixture, '4.000', '2035-08-01');
    $secondWage = earnedWorkerWage($fixture, '6.000', '2035-08-10');
    $payment = app(CreateWorkerWagePaymentAction::class)->handle(workerWagePaymentData($fixture['worker']), $this->wageUser->id);

    $reversed = app(ReverseWorkerWagePaymentAction::class)->handle($payment, '2035-09-02', $this->wageUser->id);
    $journalCount = JournalEntry::count();
    $again = app(ReverseWorkerWagePaymentAction::class)->handle($reversed, '2035-09-02', $this->wageUser->id);

    expect($again->status)->toBe(WorkerWagePaymentStatus::REVERSED)
        ->and($again->reversal_journal_entry_id)->not->toBeNull()
        ->and(JournalEntry::count())->toBe($journalCount)
        ->and((string) $firstWage->fresh()->remaining_amount)->toBe('40.00')
        ->and((string) $secondWage->fresh()->remaining_amount)->toBe('60.00')
        ->and($firstWage->fresh()->status)->toBe(ProductionWorkerWageStatus::UNPAID)
        ->and($again->allocations()->whereNull('reversed_at')->exists())->toBeFalse()
        ->and($again->reversalJournalEntry->lines->sum('debit'))->toEqual($again->reversalJournalEntry->lines->sum('credit'));
});

it('protects wage pages with dedicated permissions and supports the HTTP payment flow', function () {
    $fixture = workerWageFixture(10);
    earnedWorkerWage($fixture, '10.000', '2035-08-01');

    $this->get(route('worker-wages.index'))->assertForbidden();
    $this->wageUser->givePermissionTo(['wages.view', 'wages.pay', 'wages.reverse']);
    $this->actingAs($this->wageUser->fresh())->get(route('worker-wages.index'))->assertOk();
    $this->get(route('worker-wages.show', $fixture['worker']))->assertOk()->assertSee($fixture['worker']->name);

    $data = workerWagePaymentData($fixture['worker'], ['amount' => '25.00']);
    $this->post(route('worker-wages.payments.store'), $data)->assertSessionHasNoErrors();
    $payment = WorkerWagePayment::query()->where('submission_token', $data['submission_token'])->firstOrFail();
    $this->get(route('worker-wages.payments.show', $payment))->assertOk()->assertSee($payment->payment_number);
});

it('keeps wage expense unavailable to quick operating expenses', function () {
    $wageExpense = AccountMapping::query()->where('key', 'wages_expense')->value('account_id');
    $categories = app(QuickExpenseAccountService::class)->categories();

    expect($categories->contains('id', $wageExpense))->toBeFalse();
});
