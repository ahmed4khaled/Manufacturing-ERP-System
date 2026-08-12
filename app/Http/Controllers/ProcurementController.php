<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreateGoodsReceiptAction;
use App\Actions\Procurement\CreatePurchaseOrderAction;
use App\Actions\Procurement\CreatePurchaseReturnAction;
use App\Actions\Procurement\RecordSupplierPaymentAction;
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

class ProcurementController extends Controller
{
    public function suppliers(Request $request): View|JsonResponse
    {
        $suppliers = Supplier::query()
            ->with('contacts')
            ->when(
                $request->input('search'),
                fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
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
        $supplier->load(['contacts', 'purchaseOrders' => fn ($q) => $q->latest('order_date')->limit(10), 'goodsReceipts' => fn ($q) => $q->latest('received_at')->limit(10), 'invoices' => fn ($q) => $q->latest('invoice_date')->limit(10), 'payments' => fn ($q) => $q->latest('payment_date')->limit(10), 'returns' => fn ($q) => $q->latest('return_date')->limit(10)]);
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

    public function purchaseOrders(): View
    {
        $orders = PurchaseOrder::query()->with('supplier')->latest('order_date')->paginate(15);

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
        return view('procurement.invoices.create', ['suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(), 'orders' => PurchaseOrder::with('supplier')->latest('order_date')->get(), 'receipts' => GoodsReceipt::with('supplier')->where('status', 'confirmed')->latest('received_at')->get()]);
    }

    public function payments(): View
    {
        $payments = SupplierPayment::query()->with(['supplier', 'invoice'])->latest('payment_date')->paginate(15);

        return view('procurement.payments.index', compact('payments'));
    }

    public function createPayment(): View
    {
        return view('procurement.payments.create', ['invoices' => SupplierInvoice::with('supplier')->where('remaining_amount', '>', 0)->latest('invoice_date')->get()]);
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
            ->map(fn ($rows) => $rows->pluck('returned_quantity', 'material_id'));

        foreach ($receipts as $receipt) {
            $returnedForReceipt = $returnedByReceiptMaterial->get($receipt->id, collect());
            foreach ($receipt->items as $item) {
                $alreadyReturned = (string) $returnedForReceipt->get($item->material_id, '0');
                $item->available_to_return = bcsub((string) $item->accepted_quantity, $alreadyReturned, 3);
            }
        }

        return view('procurement.returns.create', compact('receipts'));
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

    public function storeInvoice(StoreSupplierInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $total = bcadd(bcsub((string) $data['subtotal'], (string) ($data['discount'] ?? '0'), 2), (string) ($data['tax'] ?? '0'), 2);
        abort_if(bccomp($total, '0', 2) < 0, 422, 'Invoice total cannot be negative.');

        foreach (['purchase_order_id' => 'purchase_orders', 'goods_receipt_id' => 'goods_receipts'] as $field => $table) {
            if (! empty($data[$field]) && (int) DB::table($table)->where('id', $data[$field])->value('supplier_id') !== (int) $data['supplier_id']) {
                abort(422, 'Linked document supplier must match invoice supplier.');
            }
        }

        $invoice = SupplierInvoice::create($data + [
            'invoice_number' => app(CodeGeneratorService::class)->next('PINV'),
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'total' => $total,
            'remaining_amount' => $total,
            'status' => 'unpaid',
        ]);

        return back()->with('success', "Invoice {$invoice->invoice_number} created.");
    }

    public function storePayment(StoreSupplierPaymentRequest $request, RecordSupplierPaymentAction $action): RedirectResponse
    {
        $data = $request->validated();

        $action->handle($data);

        return back()->with('success', 'Supplier payment recorded.');
    }

    public function confirmReturn(
        PurchaseReturn $purchaseReturn,
        ConfirmPurchaseReturnAction $action
    ): RedirectResponse {
        $action->handle($purchaseReturn);

        return back()->with('success', 'Purchase return confirmed and inventory posted.');
    }
}
