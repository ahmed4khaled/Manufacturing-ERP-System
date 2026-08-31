<?php

use App\Actions\Expense\CreateQuickExpenseAction;
use App\Actions\Expense\ReverseQuickExpenseAction;
use App\Enums\ExpenseFundingSource;
use App\Models\AccountMapping;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\MaterialCostAllocation;
use App\Models\MaterialCostLayer;
use App\Models\Partner;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\DocumentPostingService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(AccountingSeeder::class);
    $this->expenseCreator = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $this->payer = Partner::query()->create([
        'code' => 'QEXP-'.Str::upper(Str::random(8)),
        'name' => 'Quick expense payer',
        'profit_share' => 0,
        'is_active' => true,
    ]);
});

function quickExpenseData(Partner $payer, array $overrides = []): array
{
    return array_replace([
        'expense_date' => '2044-08-31',
        'expense_account_id' => AccountMapping::where('key', 'electricity_expense')->value('account_id'),
        'payment_account_id' => AccountMapping::where('key', 'cash')->value('account_id'),
        'amount' => '5000.00',
        'paid_by_partner_id' => $payer->id,
        'funding_source' => ExpenseFundingSource::COMPANY->value,
        'description' => 'Factory electricity',
        'reference' => 'ELEC-2044-08',
        'submission_token' => (string) Str::uuid(),
    ], $overrides);
}

it('creates and posts a company-funded cash expense with the correct balanced entry', function () {
    $expense = app(CreateQuickExpenseAction::class)->handle(
        quickExpenseData($this->payer),
        $this->expenseCreator->id,
    );

    $lines = $expense->journalEntry->lines;
    $cash = AccountMapping::where('key', 'cash')->value('account_id');

    expect($expense->status)->toBe(Expense::STATUS_POSTED)
        ->and($expense->created_by)->toBe($this->expenseCreator->id)
        ->and($expense->paid_by_partner_id)->toBe($this->payer->id)
        ->and($lines->sum('debit'))->toEqual('5000.00')
        ->and($lines->sum('credit'))->toEqual('5000.00')
        ->and($lines->firstWhere('account_id', $cash)->credit)->toBe('5000.00')
        ->and($lines->firstWhere('account_id', $cash)->partner_id)->toBeNull();
});

it('supports bank payment without creating a partner payable', function () {
    $bank = AccountMapping::where('key', 'bank')->value('account_id');
    $expense = app(CreateQuickExpenseAction::class)->handle(
        quickExpenseData($this->payer, ['payment_account_id' => $bank]),
        $this->expenseCreator->id,
    );

    $credit = $expense->journalEntry->lines->firstWhere('credit', '5000.00');

    expect($credit->account_id)->toBe($bank)
        ->and($credit->partner_id)->toBeNull()
        ->and(JournalEntryLine::where('partner_id', $this->payer->id)->count())->toBe(0);
});

it('credits the payer current account for a personally-funded expense', function () {
    $data = quickExpenseData($this->payer, [
        'funding_source' => ExpenseFundingSource::PERSONAL->value,
        'payment_account_id' => null,
    ]);

    $expense = app(CreateQuickExpenseAction::class)->handle($data, $this->expenseCreator->id);
    $partnerCurrent = AccountMapping::where('key', 'partner_current')->value('account_id');
    $credit = $expense->journalEntry->lines->firstWhere('credit', '5000.00');

    expect($expense->payment_account_id)->toBe($partnerCurrent)
        ->and($credit->account_id)->toBe($partnerCurrent)
        ->and($credit->partner_id)->toBe($this->payer->id)
        ->and($expense->creator->id)->not->toBe($expense->paidByPartner->id);
});

it('is idempotent for duplicate submissions and creates exactly one expense and journal', function () {
    $data = quickExpenseData($this->payer);
    $expenseCount = Expense::count();
    $journalCount = JournalEntry::count();

    $first = app(CreateQuickExpenseAction::class)->handle($data, $this->expenseCreator->id);
    $second = app(CreateQuickExpenseAction::class)->handle($data, $this->expenseCreator->id);

    expect($second->id)->toBe($first->id)
        ->and(Expense::count())->toBe($expenseCount + 1)
        ->and(JournalEntry::count())->toBe($journalCount + 1);
});

it('rejects closed periods and rolls back the expense document', function () {
    $year = FiscalYear::query()->create([
        'name' => 'Quick expenses closed 2045',
        'start_date' => '2045-01-01',
        'end_date' => '2045-12-31',
    ]);
    FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'name' => 'Closed 2045',
        'start_date' => '2045-01-01',
        'end_date' => '2045-12-31',
        'status' => 'closed',
    ]);
    $token = (string) Str::uuid();

    try {
        app(CreateQuickExpenseAction::class)->handle(
            quickExpenseData($this->payer, ['expense_date' => '2045-08-31', 'submission_token' => $token]),
            $this->expenseCreator->id,
        );
        $this->fail('Posting in a closed period should fail.');
    } catch (ValidationException) {
        expect(Expense::where('submission_token', $token)->exists())->toBeFalse();
    }
});

it('prevents direct edits, reverses once, and leaves a balanced audit trail', function () {
    $expense = app(CreateQuickExpenseAction::class)->handle(quickExpenseData($this->payer), $this->expenseCreator->id);

    expect(fn () => $expense->fresh()->update(['amount' => 1]))->toThrow(ValidationException::class);

    $first = app(ReverseQuickExpenseAction::class)->handle($expense, '2044-09-01', $this->expenseCreator->id);
    $journalCount = JournalEntry::count();
    $second = app(ReverseQuickExpenseAction::class)->handle($first, '2044-09-01', $this->expenseCreator->id);

    expect($second->status)->toBe(Expense::STATUS_REVERSED)
        ->and($second->reversal_journal_entry_id)->not->toBeNull()
        ->and(JournalEntry::count())->toBe($journalCount)
        ->and($second->journalEntry->fresh()->reversed_by_id)->toBe($second->reversal_journal_entry_id);
});

it('repays a personally-funded expense without recognizing a second expense', function () {
    $expense = app(CreateQuickExpenseAction::class)->handle(quickExpenseData($this->payer, [
        'funding_source' => ExpenseFundingSource::PERSONAL->value,
        'payment_account_id' => null,
    ]), $this->expenseCreator->id);
    $current = $expense->payment_account_id;
    $cash = AccountMapping::where('key', 'cash')->value('account_id');

    $this->expenseCreator->givePermissionTo('accounting.post');
    $this->actingAs($this->expenseCreator)->post(route('accounting.partners.transactions', $this->payer), [
        'type' => 'repayment',
        'date' => '2044-09-01',
        'amount' => 5000,
        'payment_account_id' => $cash,
        'description' => 'Repay personal expense',
        'submission_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    $partnerBalance = JournalEntryLine::where('partner_id', $this->payer->id)
        ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) balance')->value('balance');
    $pnl = app(AccountingReportService::class)->profitAndLoss('2044-08-31', '2044-09-01');

    expect((float) $partnerBalance)->toBe(0.0)
        ->and($pnl['operatingExpenses'])->toBe('5000.00')
        ->and(Expense::whereKey($expense->id)->count())->toBe(1);
});

it('blocks production wages and inventory accounts from quick expense categories', function () {
    foreach (['wages_expense', 'cost_of_goods_sold', 'inventory_adjustment'] as $mapping) {
        $data = quickExpenseData($this->payer, [
            'expense_account_id' => AccountMapping::where('key', $mapping)->value('account_id'),
            'submission_token' => (string) Str::uuid(),
        ]);

        expect(fn () => app(CreateQuickExpenseAction::class)->handle($data, $this->expenseCreator->id))
            ->toThrow(ValidationException::class);
    }
});

it('does not touch FIFO, production costing, or customer ledger data', function () {
    $before = [
        MaterialCostLayer::class => MaterialCostLayer::count(),
        MaterialCostAllocation::class => MaterialCostAllocation::count(),
        ProductCostLayer::class => ProductCostLayer::count(),
        ProductCostAllocation::class => ProductCostAllocation::count(),
        CustomerLedgerEntry::class => CustomerLedgerEntry::count(),
    ];

    app(CreateQuickExpenseAction::class)->handle(quickExpenseData($this->payer), $this->expenseCreator->id);

    foreach ($before as $model => $count) {
        expect($model::count())->toBe($count);
    }
});

it('keeps supplier payments in supplier settlement and outside expenses', function () {
    $supplier = Supplier::query()->create(['code' => 'QEXP-SUP-'.Str::random(6), 'name' => 'Quick expense supplier']);
    $invoice = SupplierInvoice::query()->create([
        'invoice_number' => 'QEXP-INV-'.Str::random(6),
        'supplier_id' => $supplier->id,
        'invoice_date' => '2044-08-31',
        'subtotal' => 1000,
        'total' => 1000,
        'remaining_amount' => 1000,
        'status' => 'unpaid',
    ]);
    $payment = SupplierPayment::query()->create([
        'payment_number' => 'QEXP-PAY-'.Str::random(6),
        'supplier_id' => $supplier->id,
        'supplier_invoice_id' => $invoice->id,
        'payment_type' => 'invoice',
        'payment_date' => '2044-08-31',
        'amount' => 1000,
        'payment_method' => 'cash',
    ]);
    $expenseCount = Expense::count();

    app(DocumentPostingService::class)->supplierInvoice($invoice);
    app(DocumentPostingService::class)->supplierPayment($payment);

    expect(Expense::count())->toBe($expenseCount)
        ->and(JournalEntry::where('reference_type', SupplierPayment::class)->where('reference_id', $payment->id)->count())->toBe(1);
});

it('protects the quick expense pages with their dedicated permissions', function () {
    $this->actingAs($this->expenseCreator)->get(route('accounting.expenses'))->assertForbidden();

    $this->expenseCreator->givePermissionTo('expenses.view');

    $this->actingAs($this->expenseCreator->fresh())->get(route('accounting.expenses'))->assertOk();
});

it('handles the full HTTP workflow and repeated submit token without duplicate effects', function () {
    $this->expenseCreator->givePermissionTo(['expenses.view', 'expenses.create']);
    $data = quickExpenseData($this->payer);
    $expenseCount = Expense::count();
    $journalCount = JournalEntry::count();

    $first = $this->actingAs($this->expenseCreator)->post(route('accounting.expenses.store'), $data);
    $expense = Expense::where('submission_token', $data['submission_token'])->firstOrFail();
    $first->assertRedirect(route('accounting.expenses.show', $expense));

    $this->actingAs($this->expenseCreator)->post(route('accounting.expenses.store'), $data)
        ->assertRedirect(route('accounting.expenses.show', $expense));
    $this->actingAs($this->expenseCreator)->get(route('accounting.expenses.show', $expense))
        ->assertOk()
        ->assertSee($expense->expense_number)
        ->assertSee($expense->journalEntry->entry_number);

    expect(Expense::count())->toBe($expenseCount + 1)
        ->and(JournalEntry::count())->toBe($journalCount + 1);
});
