<?php

namespace App\Http\Controllers;

use App\Actions\WorkerWage\CreateWorkerWagePaymentAction;
use App\Actions\WorkerWage\ReverseWorkerWagePaymentAction;
use App\Http\Requests\WorkerWage\IndexWorkerWageRequest;
use App\Http\Requests\WorkerWage\ReverseWorkerWagePaymentRequest;
use App\Http\Requests\WorkerWage\StoreWorkerWagePaymentRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionWorkerWage;
use App\Models\Worker;
use App\Models\WorkerWagePayment;
use App\Services\Accounting\QuickExpenseAccountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkerWageController extends Controller
{
    public function index(IndexWorkerWageRequest $request): View
    {
        $filters = $request->validated();
        $query = ProductionWorkerWage::query()
            ->with(['worker', 'productionOrder', 'operation'])
            ->when($filters['worker_id'] ?? null, fn (Builder $query, $id) => $query->where('worker_id', $id))
            ->when($filters['production_order_id'] ?? null, fn (Builder $query, $id) => $query->where('production_order_id', $id))
            ->when($filters['operation_id'] ?? null, fn (Builder $query, $id) => $query->where('production_order_operation_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('earned_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('earned_at', '<=', $date));

        return view('worker-wages.index', [
            'balances' => (clone $query)
                ->selectRaw('worker_id, SUM(gross_amount) AS gross_total, SUM(paid_amount) AS paid_total, SUM(remaining_amount) AS remaining_total')
                ->groupBy('worker_id')
                ->with('worker.latestWagePayment')
                ->orderByDesc('remaining_total')
                ->paginate(30, ['*'], 'balances_page')->withQueryString(),
            'wages' => (clone $query)->latest('earned_at')->latest('id')->paginate(30, ['*'], 'wages_page')->withQueryString(),
            'totals' => [
                'gross' => (clone $query)->sum('gross_amount'),
                'paid' => (clone $query)->sum('paid_amount'),
                'remaining' => (clone $query)->sum('remaining_amount'),
            ],
            'workers' => Worker::withTrashed()->orderBy('name')->get(),
            'operations' => ProductionOrderOperation::query()
                ->select(['id', 'operation_name', 'production_order_id'])
                ->latest('id')->limit(500)->get(),
            'productionOrders' => ProductionOrder::query()
                ->whereHas('workerWages')
                ->select(['id', 'order_number'])
                ->latest('id')->limit(500)->get(),
        ]);
    }

    public function show(Worker $worker, QuickExpenseAccountService $accounts): View
    {
        $worker->loadCount('productionWages');
        $wages = $worker->productionWages()
            ->with(['productionOrder', 'operation', 'workAssignment'])
            ->oldest('earned_at')->oldest('id')
            ->paginate(30, ['*'], 'wages_page');
        $payments = $worker->wagePayments()
            ->with(['paymentAccount', 'journalEntry'])
            ->latest('payment_date')->latest('id')
            ->paginate(20, ['*'], 'payments_page');

        return view('worker-wages.show', [
            'worker' => $worker,
            'wages' => $wages,
            'payments' => $payments,
            'outstanding' => $worker->productionWages()->sum('remaining_amount'),
            'grossTotal' => $worker->productionWages()->sum('gross_amount'),
            'paidTotal' => $worker->productionWages()->sum('paid_amount'),
            'paymentAccounts' => $accounts->paymentAccounts(),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function storePayment(
        StoreWorkerWagePaymentRequest $request,
        CreateWorkerWagePaymentAction $action,
    ): RedirectResponse {
        $payment = $action->handle($request->validated(), $request->user()?->id);

        return redirect()->route('worker-wages.payments.show', $payment)
            ->with('success', 'تم تسجيل الدفعة وتوزيعها على أقدم الاستحقاقات وترحيلها محاسبيًا.');
    }

    public function showPayment(WorkerWagePayment $workerWagePayment): View
    {
        $workerWagePayment->load([
            'worker', 'paymentAccount', 'creator', 'reverser',
            'allocations.wage.productionOrder', 'allocations.wage.operation',
            'journalEntry.lines.account', 'reversalJournalEntry.lines.account',
        ]);

        return view('worker-wages.payment', ['payment' => $workerWagePayment]);
    }

    public function reversePayment(
        ReverseWorkerWagePaymentRequest $request,
        WorkerWagePayment $workerWagePayment,
        ReverseWorkerWagePaymentAction $action,
    ): RedirectResponse {
        $action->handle($workerWagePayment, $request->validated('reversal_date'), $request->user()?->id);

        return back()->with('success', 'تم عكس دفعة الأجر وإعادة فتح الاستحقاقات.');
    }
}
