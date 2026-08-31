<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\CancelSupplierPaymentAction;
use App\Actions\Procurement\ConfirmSupplierPaymentAction;
use App\Actions\Procurement\CreateSupplierPaymentAction;
use App\Actions\Procurement\DeleteSupplierPaymentAction;
use App\Actions\Procurement\UpdateSupplierPaymentAction;
use App\Enums\SupplierPaymentStatus;
use App\Http\Requests\Procurement\StoreSupplierPaymentRequest;
use App\Http\Requests\Procurement\UpdateSupplierPaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = SupplierPayment::query()
            ->with(['supplier', 'invoice', 'allocations.supplierInvoice'])
            ->search($request->input('search'))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('date_to')))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('procurement.payments.index', compact('payments', 'suppliers'));
    }

    public function create(Request $request): View
    {
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $orders = PurchaseOrder::query()->with('supplier')->latest('order_date')->get();

        return view('procurement.payments.create', compact('suppliers', 'orders'));
    }

    public function supplierInvoices(
        Request $request,
        Supplier $supplier,
        SupplierPaymentAllocationService $allocations,
    ): JsonResponse {
        $excludePaymentId = null;
        if ($request->filled('exclude_payment_id')) {
            $excludePaymentId = SupplierPayment::query()
                ->whereKey($request->integer('exclude_payment_id'))
                ->where('supplier_id', $supplier->id)
                ->where('status', SupplierPaymentStatus::DRAFT)
                ->value('id');
        }

        return response()->json(['invoices' => $allocations->availableInvoices($supplier->id, $excludePaymentId)]);
    }

    public function store(
        StoreSupplierPaymentRequest $request,
        CreateSupplierPaymentAction $action,
    ): RedirectResponse {
        $payment = $action->handle($request->validated());

        return redirect()->route('supplier-payments.show-payment', $payment)
            ->with('success', 'تم إنشاء دفعة المورد كمسودة. راجعها ثم اضغط تأكيد للترحيل.');
    }

    public function show(
        SupplierPayment $supplierPayment,
        SupplierPaymentAllocationService $allocations,
    ): View {
        $supplierPayment->load(['supplier', 'purchaseOrder', 'invoice', 'allocations.supplierInvoice', 'creator']);
        $allocationBreakdown = $allocations->breakdown($supplierPayment);

        return view('procurement.payments.show', compact('supplierPayment', 'allocationBreakdown'));
    }

    public function print(SupplierPayment $supplierPayment): View
    {
        $supplierPayment->load(['supplier', 'purchaseOrder', 'invoice', 'allocations.supplierInvoice', 'creator']);

        return view('printing.supplier-payment', ['document' => $supplierPayment]);
    }

    public function edit(SupplierPayment $supplierPayment): View|RedirectResponse
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return redirect()->route('supplier-payments.show-payment', $supplierPayment)
                ->with('error', 'لا يمكن تعديل إلا دفعات الموردين المسودة.');
        }

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $orders = PurchaseOrder::query()->with('supplier')->latest('order_date')->get();
        $supplierPayment->load('allocations.supplierInvoice');

        return view('procurement.payments.edit', compact('supplierPayment', 'suppliers', 'orders'));
    }

    public function update(
        UpdateSupplierPaymentRequest $request,
        SupplierPayment $supplierPayment,
        UpdateSupplierPaymentAction $action,
    ): RedirectResponse {
        $action->handle($supplierPayment, $request->validated());

        return redirect()->route('supplier-payments.show-payment', $supplierPayment)
            ->with('success', 'تم تحديث مسودة دفعة المورد.');
    }

    public function confirm(
        SupplierPayment $supplierPayment,
        ConfirmSupplierPaymentAction $action,
    ): RedirectResponse {
        try {
            $action->handle($supplierPayment);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', 'تم تأكيد دفعة المورد وترحيل أثرها على الفواتير والمحاسبة.');
    }

    public function cancel(
        SupplierPayment $supplierPayment,
        CancelSupplierPaymentAction $action,
    ): RedirectResponse {
        try {
            $action->handle($supplierPayment);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', 'تم إلغاء دفعة المورد وعكس أثرها المحاسبي.');
    }

    public function destroy(
        SupplierPayment $supplierPayment,
        DeleteSupplierPaymentAction $action,
    ): RedirectResponse {
        try {
            $action->handle($supplierPayment);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('supplier-payments.index')->with('success', 'تم حذف سجل دفعة المورد.');
    }
}
