<?php

namespace App\Http\Controllers;

use App\Actions\Expense\CreateQuickExpenseAction;
use App\Actions\Expense\ReverseQuickExpenseAction;
use App\Http\Requests\Expense\IndexQuickExpenseRequest;
use App\Http\Requests\Expense\ReverseQuickExpenseRequest;
use App\Http\Requests\Expense\StoreQuickExpenseRequest;
use App\Models\Expense;
use App\Models\Partner;
use App\Services\Accounting\QuickExpenseAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuickExpenseController extends Controller
{
    public function index(IndexQuickExpenseRequest $request, QuickExpenseAccountService $accounts): View
    {
        $filters = $request->validated();
        $query = Expense::query()
            ->with(['expenseAccount', 'paymentAccount', 'paidByPartner', 'creator', 'journalEntry'])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '<=', $date))
            ->when($filters['expense_account_id'] ?? null, fn ($query, $id) => $query->where('expense_account_id', $id))
            ->when($filters['paid_by_partner_id'] ?? null, fn ($query, $id) => $query->where('paid_by_partner_id', $id))
            ->when($filters['funding_source'] ?? null, fn ($query, $source) => $query->where('funding_source', $source))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        $total = (clone $query)->where('status', Expense::STATUS_POSTED)->sum('amount');
        $expenses = $query->latest('expense_date')->latest('id')->paginate(30)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'total' => $total,
            'categories' => $accounts->categories(),
            'partners' => Partner::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(QuickExpenseAccountService $accounts): View
    {
        return view('expenses.create', [
            'categories' => $accounts->categories(),
            'paymentAccounts' => $accounts->paymentAccounts(),
            'partners' => Partner::query()->where('is_active', true)->orderBy('name')->get(),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreQuickExpenseRequest $request, CreateQuickExpenseAction $action): RedirectResponse
    {
        $expense = $action->handle($request->validated(), $request->user()?->id);

        return redirect()->route('accounting.expenses.show', $expense)
            ->with('success', 'تم تسجيل المصروف وترحيل القيد المحاسبي بنجاح.');
    }

    public function show(Expense $expense): View
    {
        $expense->load([
            'expenseAccount',
            'paymentAccount',
            'paidByPartner',
            'creator',
            'journalEntry.lines.account',
            'reversalJournalEntry.lines.account',
        ]);

        return view('expenses.show', compact('expense'));
    }

    public function reverse(
        ReverseQuickExpenseRequest $request,
        Expense $expense,
        ReverseQuickExpenseAction $action,
    ): RedirectResponse {
        $action->handle($expense, $request->validated('reversal_date'), $request->user()?->id);

        return back()->with('success', 'تم إنشاء قيد العكس. لا يُسجل مصروف جديد.');
    }
}
