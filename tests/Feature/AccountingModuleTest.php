<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\DocumentPostingService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\PeriodClosingService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(AccountingSeeder::class);
});

function accountingLines(string $amount = '100.00'): array
{
    return [
        ['account_id' => Account::where('code', '1110')->value('id'), 'debit' => $amount, 'credit' => 0],
        ['account_id' => Account::where('code', '3100')->value('id'), 'debit' => 0, 'credit' => $amount],
    ];
}

it('posts a balanced double entry and protects duplicate references', function () {
    $service = app(JournalEntryService::class);
    $header = ['entry_date' => '2026-08-30', 'description' => 'Capital', 'reference_type' => User::class, 'reference_id' => 99, 'entry_type' => 'capital'];
    $first = $service->post($header, accountingLines());
    $second = $service->post($header, accountingLines());

    expect($first->id)->toBe($second->id)
        ->and(JournalEntry::count())->toBe(1)
        ->and($first->lines->sum('debit'))->toEqual($first->lines->sum('credit'));
});

it('rejects unbalanced and invalid journal lines', function () {
    app(JournalEntryService::class)->post(
        ['entry_date' => '2026-08-30', 'description' => 'Invalid'],
        [
            ['account_id' => Account::where('code', '1110')->value('id'), 'debit' => 100, 'credit' => 0],
            ['account_id' => Account::where('code', '3100')->value('id'), 'debit' => 0, 'credit' => 90],
        ],
    );
})->throws(ValidationException::class);

it('makes posted entries immutable and reverses them once', function () {
    $service = app(JournalEntryService::class);
    $entry = $service->post(['entry_date' => '2026-08-30', 'description' => 'Capital'], accountingLines());
    $reversal = $service->reverse($entry, '2026-08-31');
    $same = $service->reverse($entry->fresh(), '2026-08-31');

    expect($same->id)->toBe($reversal->id)
        ->and($reversal->lines->sum('debit'))->toEqual($entry->lines->sum('credit'))
        ->and(fn () => $entry->fresh()->update(['description' => 'Changed']))->toThrow(ValidationException::class);
});

it('rejects posting in a closed fiscal period', function () {
    $year = FiscalYear::create(['name' => '1447/1448', 'start_date' => '2026-03-16', 'end_date' => '2027-03-04']);
    FiscalPeriod::create(['fiscal_year_id' => $year->id, 'name' => 'Ramadan year', 'start_date' => $year->start_date, 'end_date' => $year->end_date, 'status' => 'closed']);

    app(JournalEntryService::class)->post(['entry_date' => '2026-08-30', 'description' => 'Locked'], accountingLines());
})->throws(ValidationException::class);

it('builds a balanced trial balance and balance sheet from posted lines', function () {
    app(JournalEntryService::class)->post(['entry_date' => '2026-08-30', 'description' => 'Capital'], accountingLines('500.00'));
    $reports = app(AccountingReportService::class);
    $trial = $reports->trialBalance(null, '2026-08-30');
    $balanceSheet = $reports->balanceSheet('2026-08-30');
    $ledger = $reports->ledger(Account::where('code', '1110')->firstOrFail(), null, '2026-08-30');

    expect($trial['balanced'])->toBeTrue()
        ->and($trial['totals']['closing_debit'])->toBe('500.00')
        ->and($trial['totals']['closing_credit'])->toBe('500.00')
        ->and($balanceSheet['balanced'])->toBeTrue()
        ->and($ledger['openingBalance'])->toBe('0.00')
        ->and($ledger['lines']->last()->running_balance)->toBe('500.00');
});

it('protects accounting routes with permissions', function () {
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $this->actingAs($user)->get(route('accounting.accounts'))->assertForbidden();
    $user->givePermissionTo('accounting.view');
    $this->actingAs($user->fresh())->get(route('accounting.accounts'))->assertOk();
});

it('renders every accounting page for an authorized user', function () {
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->givePermissionTo(Permission::query()->where('name', 'like', 'accounting.%')->get());

    $routes = [
        'accounting.accounts',
        'accounting.journals',
        'accounting.ledger',
        'accounting.trial-balance',
        'accounting.profit-loss',
        'accounting.balance-sheet',
        'accounting.cash-bank',
        'accounting.customer-balances',
        'accounting.supplier-balances',
        'accounting.inventory-valuation',
        'accounting.cogs',
        'accounting.partners',
        'accounting.expenses',
        'accounting.periods',
    ];

    foreach ($routes as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
});

it('posts sales FIFO COGS supplier liabilities and supplier payments without duplication', function () {
    $posting = app(DocumentPostingService::class);
    $customer = Customer::create(['code' => 'C-ACC', 'name' => 'Accounting customer', 'phone' => '01000000000']);
    $invoice = SalesInvoice::create(['invoice_number' => 'ACC-S-1', 'customer_id' => $customer->id, 'invoice_date' => '2026-08-30', 'status' => 'confirmed', 'total_quantity' => 1, 'subtotal' => 100, 'total_amount' => 100, 'total_cogs' => 40]);
    $posting->salesInvoice($invoice);
    $posting->salesInvoice($invoice);

    $salesEntry = JournalEntry::where('reference_type', SalesInvoice::class)->where('reference_id', $invoice->id)->firstOrFail();
    expect($salesEntry->lines->sum('debit'))->toEqual('140.00')->and($salesEntry->lines->sum('credit'))->toEqual('140.00');

    $supplier = Supplier::create(['code' => 'S-ACC', 'name' => 'Accounting supplier']);
    $supplierInvoice = SupplierInvoice::create(['invoice_number' => 'ACC-P-1', 'supplier_id' => $supplier->id, 'invoice_date' => '2026-08-30', 'subtotal' => 75, 'total' => 75, 'remaining_amount' => 75, 'status' => 'unpaid']);
    $payment = SupplierPayment::create(['payment_number' => 'ACC-PAY-1', 'supplier_id' => $supplier->id, 'supplier_invoice_id' => $supplierInvoice->id, 'payment_type' => 'invoice', 'payment_date' => '2026-08-30', 'amount' => 25, 'payment_method' => 'bank']);
    $posting->supplierInvoice($supplierInvoice);
    $posting->supplierPayment($payment);

    $ap = Account::where('subtype', 'accounts_payable')->firstOrFail();
    $balance = JournalEntryLine::where('account_id', $ap->id)->sum('credit') - JournalEntryLine::where('account_id', $ap->id)->sum('debit');
    expect($balance)->toEqual(50.0)->and(JournalEntry::count())->toBe(3);
});

it('closes revenue and expense accounts into retained earnings without resetting balances', function () {
    $year = FiscalYear::create(['name' => '2026 manual', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    FiscalPeriod::create(['fiscal_year_id' => $year->id, 'name' => 'Full manual period', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $journal = app(JournalEntryService::class);
    $journal->post(['entry_date' => '2026-08-30', 'description' => 'Revenue'], [
        ['account_id' => Account::where('code', '1130')->value('id'), 'debit' => 1000, 'credit' => 0],
        ['account_id' => Account::where('code', '4100')->value('id'), 'debit' => 0, 'credit' => 1000],
    ]);
    $journal->post(['entry_date' => '2026-08-30', 'description' => 'Expense'], [
        ['account_id' => Account::where('code', '6100')->value('id'), 'debit' => 400, 'credit' => 0],
        ['account_id' => Account::where('code', '1110')->value('id'), 'debit' => 0, 'credit' => 400],
    ]);

    app(PeriodClosingService::class)->closeYear($year);
    $retained = Account::where('code', '3400')->firstOrFail();
    $retainedBalance = JournalEntryLine::where('account_id', $retained->id)->sum('credit') - JournalEntryLine::where('account_id', $retained->id)->sum('debit');

    expect($year->fresh()->status)->toBe('closed')->and($retainedBalance)->toEqual(600.0)
        ->and(app(AccountingReportService::class)->profitAndLoss('2026-01-01', '2026-12-31')['netProfit'])->toBe('0.00');
});

it('posts expenses once and prevents editing the posted document', function () {
    $expense = Expense::create(['expense_number' => 'EXP-TEST', 'expense_date' => '2026-08-30', 'expense_account_id' => Account::where('code', '6300')->value('id'), 'payment_account_id' => Account::where('code', '1110')->value('id'), 'amount' => 30, 'description' => 'Electricity']);
    app(DocumentPostingService::class)->expense($expense);
    expect($expense->fresh()->status)->toBe('posted')
        ->and(fn () => $expense->fresh()->update(['description' => 'Changed']))->toThrow(ValidationException::class);
});
