<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\ApprovePurchaseOrderAction;
use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreateGoodsReceiptAction;
use App\Actions\Procurement\CreatePurchaseOrderAction;
use App\Actions\Procurement\CreatePurchaseReturnAction;
use App\Actions\Procurement\RecordSupplierPaymentAction;
use App\Http\Requests\Procurement\ApprovePurchaseOrderRequest;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseReturnRequest;
use App\Http\Requests\Procurement\StoreSupplierInvoiceRequest;
use App\Http\Requests\Procurement\StoreSupplierPaymentRequest;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\Printing\DocumentPrinter;

class ProcurementController extends Controller
{
public function showSupplierPayment(
    SupplierPayment $supplierPayment
): View {
    $supplierPayment->load([
        'supplier',
        'purchaseOrder',
        'invoice',
    ]);

    return view(
        'procurement.payments.show',
        compact('supplierPayment')
    );
}

public function printSupplierPayment(
    SupplierPayment $supplierPayment
): View {
    $supplierPayment->load([
        'supplier',
        'purchaseOrder',
        'invoice',
    ]);

    return view(
        'printing.supplier-payment',
        [
            'document' => $supplierPayment,
        ]
    );
}
    public function printSupplierInvoice(
    SupplierInvoice $supplierInvoice,
    DocumentPrinter $printer
): View {
    $supplierInvoice->load([
    'supplier',
    'purchaseOrder',
    'goodsReceipt',
    'payments',
]);
    return $printer->render(
        $supplierInvoice,
        'supplier_invoice'
    );
}
public function showSupplierInvoice(
    SupplierInvoice $supplierInvoice
): View {
    $supplierInvoice->load([
        'supplier',
        'payments',
    ]);

    return view(
        'procurement.invoices.show',
        compact('supplierInvoice')
    );
}





    public function suppliers(Request $request): View|JsonResponse
    {
        $suppliers = Supplier::query()
            ->with('contacts')
            ->when(
                $request->input('search'),
                fn($query, $search) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($suppliers);
        }

        return view('suppliers.index', compact('suppliers'));
    }

    public function createSupplier(): View
    {
        return view('suppliers.create');
    }

    public function showSupplier(Supplier $supplier): View
    {
        $supplier->load(['contacts', 'purchaseOrders' => fn($q) => $q->latest('order_date')->limit(10), 'goodsReceipts' => fn($q) => $q->latest('received_at')->limit(10), 'invoices' => fn($q) => $q->latest('invoice_date')->limit(10), 'payments' => fn($q) => $q->latest('payment_date')->limit(10), 'returns' => fn($q) => $q->latest('return_date')->limit(10)]);
        $summary = ['purchases' => $supplier->purchaseOrders->sum('total'), 'invoiced' => $supplier->invoices->sum('total'), 'paid' => $supplier->payments->sum('amount')];
        $summary['outstanding'] = $summary['invoiced'] - $summary['paid'];

        return view('suppliers.show', compact('supplier', 'summary'));
    }

    public function editSupplier(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'code' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'code')->ignore($supplier)], 'phone' => 'nullable|string', 'email' => 'nullable|email', 'address' => 'nullable|string', 'tax_number' => ['nullable', 'string', Rule::unique('suppliers', 'tax_number')->ignore($supplier)], 'notes' => 'nullable|string', 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $supplier->update($data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated successfully.');
    }

    public function destroySupplier(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists() || $supplier->invoices()->exists()) {
            return back()->with('error', 'A supplier with procurement documents cannot be deleted. Set it inactive instead.');
        }
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }

    public function storeSupplierContact(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'job_title' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:255', 'email' => 'nullable|email', 'notes' => 'nullable|string', 'is_primary' => 'nullable|boolean']);
        if ($request->boolean('is_primary')) {
            $supplier->contacts()->update(['is_primary' => false]);
        }
        $supplier->contacts()->create($data + ['is_primary' => $request->boolean('is_primary')]);

        return back()->with('success', 'Supplier contact added.');
    }

    public function destroySupplierContact(Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $contact->delete();

        return back()->with('success', 'Supplier contact removed.');
    }

    public function purchaseOrders(Request $request): View
    {
        $query = PurchaseOrder::query()
            ->with('supplier')
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('procurement.purchase-orders.index', compact('orders'));
    }

    public function createPurchaseOrder(): View
    {
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $materials = Material::query()->where('status', 'active')->orderBy('name')->get();

        return view('procurement.purchase-orders.create', compact('suppliers', 'materials'));
    }

    public function goodsReceipts(): View
    {
        $receipts = GoodsReceipt::query()->with(['supplier', 'purchaseOrder'])->latest('received_at')->paginate(15);

        return view('procurement.goods-receipts.index', compact('receipts'));
    }

    public function createGoodsReceipt(): View
    {
        $orders = PurchaseOrder::query()->with(['supplier', 'items.material'])->whereIn('status', ['approved', 'partially_received'])->orderByDesc('order_date')->get();

        return view('procurement.goods-receipts.create', compact('orders'));
    }

    public function invoices(): View
    {
        $invoices = SupplierInvoice::query()->with('supplier')->latest('invoice_date')->paginate(15);

        return view('procurement.invoices.index', compact('invoices'));
    }
    public function createInvoice(): View
    {
        $suppliers = Supplier::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->latest('created_at')
            ->get();

        $receipts = GoodsReceipt::query()
            ->with([
                'supplier',
                'items.material',
            ])
            ->where('status', 'confirmed')
            ->latest('received_at')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Advance Payments
    |--------------------------------------------------------------------------
    |
    | Get total advance payments made against each Purchase Order.
    |
    */

        $advancePayments = SupplierPayment::query()
            ->select(
                'purchase_order_id',
                'supplier_id'
            )
            ->selectRaw('SUM(amount) as total_advance')
            ->where('payment_type', 'advance')
            ->whereNotNull('purchase_order_id')
            ->groupBy(
                'purchase_order_id',
                'supplier_id'
            )
            ->get()
            ->keyBy('purchase_order_id');

        return view(
            'procurement.invoices.create',
            compact(
                'suppliers',
                'orders',
                'receipts',
                'advancePayments'
            )
        );
    }

    public function payments(): View
    {
        $payments = SupplierPayment::query()->with(['supplier', 'invoice'])->latest('payment_date')->paginate(15);

        return view('procurement.payments.index', compact('payments'));
    }

    public function createPayment(): View
    {
        $suppliers = Supplier::query()
            ->orderBy('name')
            ->get();

        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->latest('created_at')
            ->get();

        $invoices = SupplierInvoice::query()
            ->where('remaining_amount', '>', 0)
            ->with('supplier')
            ->latest('invoice_date')
            ->get();

        return view('procurement.payments.create', compact(
            'suppliers',
            'orders',
            'invoices'
        ));
    }

    public function returns(): View
    {
        $returns = PurchaseReturn::query()->with('supplier')->latest('return_date')->paginate(15);

        return view('procurement.returns.index', compact('returns'));
    }

    public function createReturn(): View
    {
        $receipts = GoodsReceipt::query()
            ->with(['supplier', 'items.material'])
            ->where('status', 'confirmed')
            ->latest('received_at')
            ->get();

        $returnedByReceiptMaterial = PurchaseReturnItem::query()
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->whereIn('purchase_returns.goods_receipt_id', $receipts->pluck('id'))
            ->whereIn('purchase_returns.status', ['draft', 'confirmed'])
            ->groupBy('purchase_returns.goods_receipt_id', 'purchase_return_items.material_id')
            ->selectRaw('purchase_returns.goods_receipt_id, purchase_return_items.material_id, SUM(purchase_return_items.quantity) as returned_quantity')
            ->get()
            ->groupBy('goods_receipt_id')
            ->map(fn($rows) => $rows->pluck('returned_quantity', 'material_id'));

        foreach ($receipts as $receipt) {
            $returnedForReceipt = $returnedByReceiptMaterial->get($receipt->id, collect());
            foreach ($receipt->items as $item) {
                $alreadyReturned = (string) $returnedForReceipt->get($item->material_id, '0');
                $item->available_to_return = bcsub((string) $item->accepted_quantity, $alreadyReturned, 3);
            }
        }
        $receiptData = $receipts->mapWithKeys(function ($receipt) {
            return [
                $receipt->id => $receipt->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'material_id' => $item->material_id,
                        'code' => $item->material->code,
                        'label' => $item->material->name,
                        'unit' => $item->material->unit->label(),
                        'accepted' => (string) $item->accepted_quantity,
                        'already_returned' => (string) ($item->already_returned_quantity ?? '0'),
                        'available' => (string) ($item->available_to_return ?? $item->accepted_quantity),
                        'unit_cost' => (string) $item->unit_cost,
                    ];
                })->values()->all(),
            ];
        })->all();

        return view('procurement.returns.create', compact('receipts', 'receiptData'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:suppliers,code',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'tax_number' => 'nullable|string|unique:suppliers,tax_number',
            'notes' => 'nullable|string',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        Supplier::create($data + ['status' => $data['status'] ?? 'active']);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function storePurchaseOrder(StorePurchaseOrderRequest $request, CreatePurchaseOrderAction $action): RedirectResponse
    {
        $data = $request->validated();

        return redirect()
            ->route('purchase-orders.show', $action->handle($data))
            ->with('success', 'Purchase order created.');
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('supplier', 'items.material', 'receipts.items');

        return view('procurement.purchase-orders.show', compact('purchaseOrder'));
    }

    public function approvePurchaseOrder(
        PurchaseOrder $purchaseOrder,
        ApprovePurchaseOrderRequest $request,
        ApprovePurchaseOrderAction $action
    ): RedirectResponse {
        $action->handle($purchaseOrder);

        return back()->with('success', 'Purchase order approved successfully.');
    }

    public function confirmReceipt(
        GoodsReceipt $goodsReceipt,
        ConfirmGoodsReceiptAction $action
    ): RedirectResponse {

        $action->handle($goodsReceipt);


        return back()->with('success', 'Goods receipt confirmed and inventory posted.');
    }

    public function storeGoodsReceipt(StoreGoodsReceiptRequest $request, CreateGoodsReceiptAction $action): RedirectResponse
    {
        $data = $request->validated();

        $receipt = $action->handle($data);

        return redirect()->route('goods-receipts.show', $receipt)->with('success', 'Goods receipt created.');
    }

    public function showGoodsReceipt(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load(['supplier', 'purchaseOrder', 'items.material']);

        return view('procurement.goods-receipts.show', compact('goodsReceipt'));
    }

    public function storeReturn(StorePurchaseReturnRequest $request, CreatePurchaseReturnAction $action): RedirectResponse
    {
        $data = $request->validated();

        $return = $action->handle($data);

        return redirect()->route('purchase-returns.show', $return)->with('success', 'Purchase return saved.');
    }

    public function showReturn(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load(['supplier', 'goodsReceipt', 'items.material']);

        return view('procurement.returns.show', compact('purchaseReturn'));
    }

    public function storeInvoice(
        StoreSupplierInvoiceRequest $request
    ): RedirectResponse {

        $data = $request->validated();

        $receipt = null;

        /*
    |--------------------------------------------------------------------------
    | Validate Purchase Order
    |--------------------------------------------------------------------------
    */

        $po = null;

        if (!empty($data['purchase_order_id'])) {

            $po = PurchaseOrder::query()
                ->findOrFail($data['purchase_order_id']);

            if ((int) $po->supplier_id !== (int) $data['supplier_id']) {
                abort(
                    422,
                    'Purchase order supplier must match invoice supplier.'
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Validate Goods Receipt
    |--------------------------------------------------------------------------
    */

        if (!empty($data['goods_receipt_id'])) {

            $receipt = GoodsReceipt::query()
                ->with('items')
                ->where('id', $data['goods_receipt_id'])
                ->where('status', 'confirmed')
                ->firstOrFail();

            /*
        | Receipt supplier must match invoice supplier
        */

            if ((int) $receipt->supplier_id !== (int) $data['supplier_id']) {
                abort(
                    422,
                    'Goods receipt supplier must match invoice supplier.'
                );
            }

            /*
        | If PO is selected, it must be the PO of this receipt
        */

            if (
                !empty($data['purchase_order_id']) &&
                (int) $receipt->purchase_order_id !==
                (int) $data['purchase_order_id']
            ) {
                abort(
                    422,
                    'Purchase order must match the selected goods receipt.'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Calculate Subtotal From ACCEPTED Quantity Only
        |--------------------------------------------------------------------------
        */

            $subtotal = '0';

            foreach ($receipt->items as $item) {

                $acceptedQuantity = (string) $item->accepted_quantity;
                $unitCost = (string) $item->unit_cost;

                $lineTotal = bcmul(
                    $acceptedQuantity,
                    $unitCost,
                    2
                );

                $subtotal = bcadd(
                    $subtotal,
                    $lineTotal,
                    2
                );
            }

            /*
        | Never trust subtotal coming from the form
        */

            $data['subtotal'] = $subtotal;
        }

        /*
    |--------------------------------------------------------------------------
    | Subtotal
    |--------------------------------------------------------------------------
    */

        $subtotal = (string) ($data['subtotal'] ?? '0');

        /*
    |--------------------------------------------------------------------------
    | Discount & Tax
    |--------------------------------------------------------------------------
    */

        $discount = (string) ($data['discount'] ?? '0');
        $tax = (string) ($data['tax'] ?? '0');

        /*
    |--------------------------------------------------------------------------
    | Validate Discount
    |--------------------------------------------------------------------------
    */

        if (bccomp($discount, '0', 2) < 0) {
            abort(
                422,
                'Discount cannot be negative.'
            );
        }

        if (bccomp($discount, $subtotal, 2) > 0) {
            abort(
                422,
                'Discount cannot exceed invoice subtotal.'
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Validate Tax
    |--------------------------------------------------------------------------
    */

        if (bccomp($tax, '0', 2) < 0) {
            abort(
                422,
                'Tax cannot be negative.'
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

        $afterDiscount = bcsub(
            $subtotal,
            $discount,
            2
        );

        $total = bcadd(
            $afterDiscount,
            $tax,
            2
        );

        /*
    |--------------------------------------------------------------------------
    | Final Total Validation
    |--------------------------------------------------------------------------
    */

        if (bccomp($total, '0', 2) < 0) {
            abort(
                422,
                'Invoice total cannot be negative.'
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Calculate Advance Payments
    |--------------------------------------------------------------------------
    */

        $advancePaid = '0';

        if ($po) {

            $advancePaid = (string) SupplierPayment::query()
                ->where('purchase_order_id', $po->id)
                ->where('supplier_id', $po->supplier_id)
                ->where('payment_type', 'advance')
                ->sum('amount');
        }

        /*
    |--------------------------------------------------------------------------
    | Calculate Remaining Amount
    |--------------------------------------------------------------------------
    */

        if (bccomp($advancePaid, $total, 2) > 0) {

            abort(
                422,
                'Advance payments cannot exceed invoice total.'
            );
        }

        $remainingAmount = bcsub(
            $total,
            $advancePaid,
            2
        );

        /*
    |--------------------------------------------------------------------------
    | Determine Invoice Status
    |--------------------------------------------------------------------------
    */

        $status = match (true) {

            bccomp($remainingAmount, '0', 2) === 0 =>
            'paid',

            bccomp($advancePaid, '0', 2) > 0 =>
            'partially_paid',

            default =>
            'unpaid',
        };

        /*
    |--------------------------------------------------------------------------
    | Create Supplier Invoice
    |--------------------------------------------------------------------------
    */

        $invoice = SupplierInvoice::create([

            ...$data,

            'invoice_number' =>
            app(CodeGeneratorService::class)
                ->next('PINV'),

            'subtotal' =>
            $subtotal,

            'discount' =>
            $discount,

            'tax' =>
            $tax,

            'total' =>
            $total,

            'remaining_amount' =>
            $remainingAmount,

            'status' =>
            $status,
        ]);

        return back()->with(
            'success',
            "Invoice {$invoice->invoice_number} created."
        );
    }
    public function storePayment(
        StoreSupplierPaymentRequest $request
    ): RedirectResponse {

        $data = $request->validated();

        /*
    |--------------------------------------------------------------------------
    | Validate Supplier
    |--------------------------------------------------------------------------
    */

        $supplier = Supplier::findOrFail($data['supplier_id']);

        /*
    |--------------------------------------------------------------------------
    | Advance Payment
    |--------------------------------------------------------------------------
    */

        if ($data['payment_type'] === 'advance') {

            $po = PurchaseOrder::query()
                ->findOrFail($data['purchase_order_id']);

            /*
        |--------------------------------------------------------------------------
        | Validate PO Supplier
        |--------------------------------------------------------------------------
        */

            if ((int) $po->supplier_id !== (int) $supplier->id) {
                abort(
                    422,
                    'Purchase order supplier must match payment supplier.'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Create Advance Payment
        |--------------------------------------------------------------------------
        */

            $payment = SupplierPayment::create([

                'payment_number' =>
                app(CodeGeneratorService::class)->next('SPAY'),

                'supplier_id' =>
                $supplier->id,

                'purchase_order_id' =>
                $po->id,

                'supplier_invoice_id' =>
                null,

                'payment_type' =>
                'advance',

                'payment_date' =>
                $data['payment_date'],

                'amount' =>
                $data['amount'],

                'payment_method' =>
                $data['payment_method'],

                'reference' =>
                $data['reference'] ?? null,

                'notes' =>
                $data['notes'] ?? null,
            ]);

            return back()->with(
                'success',
                "Advance payment {$payment->payment_number} recorded."
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Invoice Payment
    |--------------------------------------------------------------------------
    |
    | Supports:
    | - Full payment
    | - Partial payment
    | - Multiple payments for the same invoice
    |
    */

        DB::transaction(function () use ($data, $supplier) {

            /*
        |--------------------------------------------------------------------------
        | Lock Invoice
        |--------------------------------------------------------------------------
        */

            $invoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($data['supplier_invoice_id']);

            /*
        |--------------------------------------------------------------------------
        | Validate Invoice Supplier
        |--------------------------------------------------------------------------
        */

            if ((int) $invoice->supplier_id !== (int) $supplier->id) {
                abort(
                    422,
                    'Invoice supplier must match payment supplier.'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Payment Amount
        |--------------------------------------------------------------------------
        */

            $amount = (string) $data['amount'];

            $remaining = (string) $invoice->remaining_amount;

            /*
        |--------------------------------------------------------------------------
        | Validate Payment Amount
        |--------------------------------------------------------------------------
        */

            if (bccomp($amount, '0', 2) <= 0) {
                abort(
                    422,
                    'Payment amount must be greater than zero.'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Payment Cannot Exceed Remaining Amount
        |--------------------------------------------------------------------------
        */

            if (bccomp($amount, $remaining, 2) > 0) {
                abort(
                    422,
                    'Payment amount cannot exceed invoice remaining amount.'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Calculate New Remaining Amount
        |--------------------------------------------------------------------------
        */

            $newRemaining = bcsub(
                $remaining,
                $amount,
                2
            );

            /*
        |--------------------------------------------------------------------------
        | Determine Invoice Status
        |--------------------------------------------------------------------------
        */

            $status = bccomp(
                $newRemaining,
                '0',
                2
            ) === 0
                ? 'paid'
                : 'partially_paid';

            /*
        |--------------------------------------------------------------------------
        | Create Supplier Payment
        |--------------------------------------------------------------------------
        */

            SupplierPayment::create([

                'payment_number' =>
                app(CodeGeneratorService::class)->next('SPAY'),

                'supplier_id' =>
                $invoice->supplier_id,

                'purchase_order_id' =>
                $invoice->purchase_order_id,

                'supplier_invoice_id' =>
                $invoice->id,

                'payment_type' =>
                'invoice',

                'payment_date' =>
                $data['payment_date'],

                'amount' =>
                $amount,

                'payment_method' =>
                $data['payment_method'],

                'reference' =>
                $data['reference'] ?? null,

                'notes' =>
                $data['notes'] ?? null,
            ]);

            /*
        |--------------------------------------------------------------------------
        | Update Invoice Balance
        |--------------------------------------------------------------------------
        */

            $invoice->update([

                'remaining_amount' =>
                $newRemaining,

                'status' =>
                $status,
            ]);
        });

        /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

        return back()->with(
            'success',
            'Supplier payment recorded successfully.'
        );
    }

    public function confirmReturn(
        PurchaseReturn $purchaseReturn,
        ConfirmPurchaseReturnAction $action
    ): RedirectResponse {
        $action->handle($purchaseReturn);

        return back()->with('success', 'Purchase return confirmed and inventory posted.');
    }
    public function supplierLedger(Supplier $supplier)
    {
        $invoices = $supplier->invoices()
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payments = $supplier->payments()
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $returns = $supplier->returns()
            ->where('status', 'confirmed')
            ->orderBy('return_date')
            ->orderBy('id')
            ->get();

        $transactions = collect();

        foreach ($invoices as $invoice) {
            $transactions->push([
                'date' => $invoice->invoice_date,
                'reference' => $invoice->invoice_number,
                'type' => 'Invoice',
                'description' => 'Supplier Invoice',
                'debit' => (float) $invoice->total,
                'credit' => 0,
                'model' => $invoice,
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->payment_date,
                'reference' => 'PAY-' . $payment->id,
                'type' => 'Payment',
                'description' => $payment->payment_method ?? 'Supplier Payment',
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'model' => $payment,
            ]);
        }

        foreach ($returns as $return) {
            $amount = $return->items->sum(function ($item) {
                return (float) $item->quantity * (float) $item->unit_cost;
            });

            $transactions->push([
                'date' => $return->return_date,
                'reference' => $return->return_number,
                'type' => 'Purchase Return',
                'description' => $return->reason ?? 'Purchase Return',
                'debit' => 0,
                'credit' => $amount,
                'model' => $return,
            ]);
        }

        $transactions = $transactions
            ->sortBy([
                ['date', 'asc'],
                ['reference', 'asc'],
            ])
            ->values();

        $balance = 0;

        $transactions = $transactions->map(function ($transaction) use (&$balance) {
            $balance += $transaction['debit'];
            $balance -= $transaction['credit'];

            $transaction['balance'] = $balance;

            return $transaction;
        });

        return view('suppliers.ledger', [
            'supplier' => $supplier,
            'transactions' => $transactions,
            'currentBalance' => $balance,
        ]);
    }
}
