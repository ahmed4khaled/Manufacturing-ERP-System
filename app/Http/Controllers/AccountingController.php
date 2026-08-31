<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\MaterialCostLayer;
use App\Models\Partner;
use App\Models\ProductCostLayer;
use App\Models\Supplier;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\PeriodClosingService;
use App\Services\Accounting\QuickExpenseAccountService;
use App\Services\CustomerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function accounts(): View
    {
        return view('accounting.accounts', ['accounts' => Account::query()->with('parent')->orderBy('code')->get()]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounts,code'], 'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Account::TYPES)], 'subtype' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:accounts,id'], 'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'], 'allow_posting' => ['nullable', 'boolean'],
        ]);
        Account::query()->create($data + ['is_active' => $request->boolean('is_active', true), 'allow_posting' => $request->boolean('allow_posting')]);

        return back()->with('success', 'تم إنشاء الحساب.');
    }

    public function journals(Request $request): View
    {
        $entries = JournalEntry::query()->with(['lines.account'])->when($request->integer('entry_id'), fn ($q, $id) => $q->whereKey($id))->when($request->date_from, fn ($q, $date) => $q->whereDate('entry_date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('entry_date', '<=', $date))->latest('entry_date')->latest('id')->paginate(30)->withQueryString();

        return view('accounting.journals', ['entries' => $entries, 'accounts' => Account::posting()->orderBy('code')->get()]);
    }

    public function storeJournal(Request $request, JournalEntryService $journal): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'description' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);
        $journal->post(['entry_date' => $data['entry_date'], 'description' => $data['description'], 'entry_type' => 'manual'], $data['lines']);

        return back()->with('success', 'تم ترحيل القيد المتوازن.');
    }

    public function reverseJournal(Request $request, JournalEntry $journalEntry, JournalEntryService $journal): RedirectResponse
    {
        $data = $request->validate(['entry_date' => ['nullable', 'date']]);
        $journal->reverse($journalEntry, $data['entry_date'] ?? null);

        return back()->with('success', 'تم إنشاء قيد العكس.');
    }

    public function ledger(Request $request, AccountingReportService $reports): View
    {
        $account = $request->filled('account_id') ? Account::query()->findOrFail($request->integer('account_id')) : Account::posting()->orderBy('code')->first();
        $report = $account ? $reports->ledger($account, $request->date_from, $request->date_to) : ['openingBalance' => '0.00', 'lines' => collect()];

        return view('accounting.ledger', compact('account', 'report') + ['accounts' => Account::posting()->orderBy('code')->get()]);
    }

    public function trialBalance(Request $request, AccountingReportService $reports): View
    {
        return view('accounting.trial-balance', ['report' => $reports->trialBalance($request->date_from, $request->date_to)]);
    }

    public function profitLoss(Request $request, AccountingReportService $reports): View
    {
        return view('accounting.financial-report', ['title' => 'قائمة الأرباح والخسائر', 'kind' => 'profit-loss', 'report' => $reports->profitAndLoss($request->date_from, $request->date_to)]);
    }

    public function balanceSheet(Request $request, AccountingReportService $reports): View
    {
        return view('accounting.financial-report', ['title' => 'الميزانية العمومية', 'kind' => 'balance-sheet', 'report' => $reports->balanceSheet($request->date_to)]);
    }

    public function balances(Request $request, AccountingReportService $reports, string $kind): View
    {
        $map = ['cash-bank' => ['asset', ['cash', 'bank']], 'customers' => ['asset', ['accounts_receivable']], 'suppliers' => ['liability', ['accounts_payable']]];
        abort_unless(isset($map[$kind]), 404);
        [$type, $subtypes] = $map[$kind];
        $accounts = $reports->balancesByType($type, $request->date_from, $request->date_to)->whereIn('subtype', $subtypes);
        $parties = null;
        if ($kind === 'customers') {
            $operational = app(CustomerLedgerService::class)->getBalances();
            $gl = JournalEntryLine::query()->whereNotNull('customer_id')->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
                ->selectRaw('customer_id, SUM(debit) - SUM(credit) balance')->groupBy('customer_id')->pluck('balance', 'customer_id');
            $parties = Customer::query()->orderBy('name')->get()->map(function ($customer) use ($operational, $gl) {
                $customer->operational_balance = bcadd((string) ($operational[$customer->id] ?? 0), '0', 2);
                $customer->general_ledger_balance = bcadd((string) ($gl[$customer->id] ?? 0), '0', 2);
                $customer->variance = bcsub($customer->operational_balance, $customer->general_ledger_balance, 2);

                return $customer;
            });
        } elseif ($kind === 'suppliers') {
            $gl = JournalEntryLine::query()->whereNotNull('supplier_id')->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
                ->selectRaw('supplier_id, SUM(credit) - SUM(debit) balance')->groupBy('supplier_id')->pluck('balance', 'supplier_id');
            $parties = Supplier::query()->withSum('invoices as invoice_total', 'total')->withSum('payments as payment_total', 'amount')->with(['returns' => fn ($q) => $q->where('status', 'confirmed')->with('items')])->orderBy('name')->get()->map(function ($supplier) use ($gl) {
                $returns = $supplier->returns->sum(fn ($return) => $return->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_cost));
                $supplier->operational_balance = bcsub(bcsub((string) ($supplier->invoice_total ?? 0), (string) ($supplier->payment_total ?? 0), 2), (string) $returns, 2);
                $supplier->general_ledger_balance = bcadd((string) ($gl[$supplier->id] ?? 0), '0', 2);
                $supplier->variance = bcsub($supplier->operational_balance, $supplier->general_ledger_balance, 2);

                return $supplier;
            });
        }

        return view('accounting.balances', ['title' => match ($kind) {
            'cash-bank' => 'الخزينة والبنوك', 'customers' => 'حسابات العملاء', default => 'حسابات الموردين'
        }, 'accounts' => $accounts, 'parties' => $parties]);
    }

    public function inventoryValuation(): View
    {
        $materials = MaterialCostLayer::query()->with('material')->where('remaining_quantity', '>', 0)->get();
        $products = ProductCostLayer::query()->with('productVariant')->where('remaining_quantity', '>', 0)->get();

        return view('accounting.inventory-valuation', compact('materials', 'products'));
    }

    public function cogs(Request $request, AccountingReportService $reports): View
    {
        return view('accounting.balances', ['title' => 'تكلفة البضاعة المباعة FIFO', 'accounts' => $reports->balancesByType('expense', $request->date_from, $request->date_to)->where('subtype', 'cost_of_goods_sold'), 'parties' => null]);
    }

    public function partners(QuickExpenseAccountService $quickExpenseAccounts): View
    {
        $partners = Partner::query()->orderBy('code')->get()->each(function ($partner): void {
            $sum = JournalEntryLine::query()->where('partner_id', $partner->id)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->selectRaw('COALESCE(SUM(credit),0) credit, COALESCE(SUM(debit),0) debit')->first();
            $partner->balance = bcsub((string) $sum->credit, (string) $sum->debit, 2);
        });

        return view('accounting.partners', [
            'partners' => $partners,
            'paymentAccounts' => $quickExpenseAccounts->paymentAccounts(),
        ]);
    }

    public function storePartner(Request $request): RedirectResponse
    {
        Partner::query()->create($request->validate(['code' => ['required', 'max:30', 'unique:partners,code'], 'name' => ['required', 'max:255'], 'profit_share' => ['required', 'numeric', 'min:0', 'max:100']]));

        return back()->with('success', 'تم إنشاء الشريك.');
    }

    public function partnerTransaction(
        Request $request,
        Partner $partner,
        JournalEntryService $journal,
        QuickExpenseAccountService $quickExpenseAccounts,
    ): RedirectResponse {
        $data = $request->validate([
            'type' => ['required', Rule::in(['capital', 'current', 'withdrawal', 'repayment', 'profit_distribution'])],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_account_id' => ['nullable', 'required_if:type,repayment', 'exists:accounts,id'],
            'description' => ['required', 'string'],
            'submission_token' => ['required', 'uuid'],
        ]);
        $cash = $journal->account('cash')->id;
        $current = $quickExpenseAccounts->partnerCurrentAccount($partner)->id;
        $mapped = match ($data['type']) {
            'capital' => [$cash, $partner->capital_account_id ?: $journal->account('partner_capital')->id],
            'current' => [$cash, $current],
            'withdrawal' => [$partner->withdrawals_account_id ?: $journal->account('partner_withdrawals')->id, $cash],
            'repayment' => [$current, $quickExpenseAccounts->companyPaymentAccount((int) $data['payment_account_id'])->id],
            'profit_distribution' => [$journal->account('retained_earnings')->id, $current],
        };
        $partnerLine = in_array($data['type'], ['withdrawal', 'repayment'], true) ? 0 : 1;
        $journal->post(['entry_date' => $data['date'], 'description' => $data['description'], 'reference_type' => Partner::class, 'reference_id' => $partner->id, 'entry_type' => $data['type'].'_'.$data['submission_token']], [
            ['account_id' => $mapped[0], 'debit' => $data['amount'], 'credit' => 0, 'partner_id' => $partnerLine === 0 ? $partner->id : null],
            ['account_id' => $mapped[1], 'debit' => 0, 'credit' => $data['amount'], 'partner_id' => $partnerLine === 1 ? $partner->id : null],
        ]);

        return back()->with('success', 'تم ترحيل حركة الشريك.');
    }

    public function periods(): View
    {
        return view('accounting.periods', ['years' => FiscalYear::query()->with('periods')->latest('start_date')->get()]);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'unique:fiscal_years,name'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        DB::transaction(function () use ($data): void {
            $year = FiscalYear::query()->create($data);
            $year->periods()->create(['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date']]);
        });

        return back()->with('success', 'تم إنشاء السنة والفترة المالية.');
    }

    public function closePeriod(FiscalPeriod $fiscalPeriod, PeriodClosingService $closing): RedirectResponse
    {
        $closing->closePeriod($fiscalPeriod);

        return back()->with('success', 'تم إغلاق الفترة.');
    }

    public function closeYear(FiscalYear $fiscalYear, PeriodClosingService $closing): RedirectResponse
    {
        $closing->closeYear($fiscalYear);

        return back()->with('success', 'تم إغلاق السنة وترحيل صافي النتيجة.');
    }
}
