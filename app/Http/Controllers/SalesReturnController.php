<?php

namespace App\Http\Controllers;

use App\Actions\SalesReturn\CancelSalesReturnAction;
use App\Actions\SalesReturn\ConfirmSalesReturnAction;
use App\Actions\SalesReturn\CreateSalesReturnAction;
use App\Actions\SalesReturn\DeleteSalesReturnAction;
use App\Actions\SalesReturn\UpdateSalesReturnAction;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Http\Requests\SalesReturn\StoreSalesReturnRequest;
use App\Http\Requests\SalesReturn\UpdateSalesReturnRequest;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\Printing\DocumentPrinter;
use App\Services\SalesReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    public function index(Request $request): View
    {
        $salesReturns = SalesReturn::query()
            ->with(['salesInvoice', 'customer'])
            ->search($request->input('search'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->latest('return_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('sales-returns.index', compact('salesReturns', 'customers'));
    }

    public function create(Request $request, SalesReturnService $salesReturnService): View
    {
        $invoices = SalesInvoice::query()
            ->with('customer')
            ->where('status', SalesInvoiceStatus::CONFIRMED)
            ->latest('invoice_date')
            ->latest('id')
            ->get();

        $invoice = null;
        $eligibleItems = collect();

        if ($request->filled('sales_invoice_id')) {
            $invoice = SalesInvoice::query()
                ->with(['customer', 'items'])
                ->where('status', SalesInvoiceStatus::CONFIRMED)
                ->findOrFail($request->integer('sales_invoice_id'));
            $eligibleItems = $salesReturnService->invoiceItemsWithEligibility($invoice);
        }

        return view('sales-returns.create', compact('invoices', 'invoice', 'eligibleItems'));
    }

    public function store(
        StoreSalesReturnRequest $request,
        CreateSalesReturnAction $action,
    ): RedirectResponse {
        $salesReturn = $action->handle($request->validated());

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', 'تم إنشاء مسودة مرتجع المبيعات بنجاح.');
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load(['salesInvoice', 'customer', 'creator', 'items']);

        return view('sales-returns.show', compact('salesReturn'));
    }

    public function edit(
        SalesReturn $salesReturn,
        SalesReturnService $salesReturnService,
    ): View|RedirectResponse {
        if ($salesReturn->status !== SalesReturnStatus::DRAFT) {
            return redirect()
                ->route('sales-returns.show', $salesReturn)
                ->with('error', 'لا يمكن تعديل إلا مرتجعات المبيعات المسودة.');
        }

        $salesReturn->load(['salesInvoice.customer', 'items']);
        $invoice = $salesReturn->salesInvoice;
        $eligibleItems = $salesReturnService->invoiceItemsWithEligibility($invoice, $salesReturn->id);

        return view('sales-returns.edit', compact('salesReturn', 'invoice', 'eligibleItems'));
    }

    public function update(
        UpdateSalesReturnRequest $request,
        SalesReturn $salesReturn,
        UpdateSalesReturnAction $action,
    ): RedirectResponse {
        $action->handle($salesReturn, $request->validated());

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', 'تم تحديث مرتجع المبيعات بنجاح.');
    }

    public function destroy(
        SalesReturn $salesReturn,
        DeleteSalesReturnAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesReturn);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('sales-returns.show', $salesReturn)
                ->with('error', $exception->validator->errors()->first());
        }

        return redirect()
            ->route('sales-returns.index')
            ->with('success', 'تم حذف مسودة مرتجع المبيعات بنجاح.');
    }
    public function confirm(
        SalesReturn $salesReturn,
        ConfirmSalesReturnAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesReturn);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('sales-returns.show', $salesReturn)
                ->with('error', $exception->validator->errors()->first());
        }

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', 'تم تأكيد مرتجع المبيعات وترحيل آثاره بنجاح.');
    }

    public function cancel(
        SalesReturn $salesReturn,
        CancelSalesReturnAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesReturn);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('sales-returns.show', $salesReturn)
                ->with('error', $exception->validator->errors()->first());
        }

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', 'تم إلغاء مرتجع المبيعات وعكس آثاره بنجاح.');
    }

    public function print(SalesReturn $salesReturn, DocumentPrinter $printer): View
    {
        $salesReturn->load(['salesInvoice', 'customer', 'creator', 'items']);

        return $printer->render($salesReturn, 'sales_return');
    }
}
