<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreatePurchaseOrderAction;
use App\Actions\Procurement\RecordSupplierPaymentAction;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\ProductVariant;
use App\Services\CodeGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:suppliers.view')->only(['suppliers','showSupplier']);
        $this->middleware('permission:suppliers.create')->only(['createSupplier','storeSupplier','storeSupplierContact']);
        $this->middleware('permission:suppliers.update')->only(['editSupplier','updateSupplier']);
        $this->middleware('permission:suppliers.delete')->only(['destroySupplier','destroySupplierContact']);
        $this->middleware('permission:purchase_orders.view')->only(['purchaseOrders','showPurchaseOrder']);
        $this->middleware('permission:purchase_orders.create')->only(['createPurchaseOrder','storePurchaseOrder']);
        $this->middleware('permission:goods_receipts.view')->only(['goodsReceipts','showGoodsReceipt']);
        $this->middleware('permission:goods_receipts.create')->only(['createGoodsReceipt','storeGoodsReceipt']);
        $this->middleware('permission:goods_receipts.confirm')->only('confirmReceipt');
        $this->middleware('permission:supplier_invoices.view')->only('invoices');
        $this->middleware('permission:supplier_invoices.create')->only(['createInvoice','storeInvoice']);
        $this->middleware('permission:supplier_payments.view')->only('payments');
        $this->middleware('permission:supplier_payments.create')->only(['createPayment','storePayment']);
        $this->middleware('permission:purchase_returns.view')->only('returns');
        $this->middleware('permission:purchase_returns.create')->only('createReturn');
        $this->middleware('permission:purchase_returns.confirm')->only('confirmReturn');
    }
    public function suppliers(Request $request): View|\Illuminate\Http\JsonResponse
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

    public function editSupplier(Supplier $supplier): View { return view('suppliers.edit', compact('supplier')); }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['name'=>'required|string|max:255','code'=>['required','string','max:255', Rule::unique('suppliers','code')->ignore($supplier)],'phone'=>'nullable|string','email'=>'nullable|email','address'=>'nullable|string','tax_number'=>['nullable','string',Rule::unique('suppliers','tax_number')->ignore($supplier)],'notes'=>'nullable|string','status'=>['required',Rule::in(['active','inactive'])]]);
        $supplier->update($data);
        return redirect()->route('suppliers.show',$supplier)->with('success','Supplier updated successfully.');
    }

    public function destroySupplier(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists() || $supplier->invoices()->exists()) return back()->with('error','A supplier with procurement documents cannot be deleted. Set it inactive instead.');
        $supplier->delete(); return redirect()->route('suppliers.index')->with('success','Supplier deleted successfully.');
    }

    public function storeSupplierContact(Request $request, Supplier $supplier): RedirectResponse
    {
        $data=$request->validate(['name'=>'required|string|max:255','job_title'=>'nullable|string|max:255','phone'=>'nullable|string|max:255','email'=>'nullable|email','notes'=>'nullable|string','is_primary'=>'nullable|boolean']);
        if ($request->boolean('is_primary')) $supplier->contacts()->update(['is_primary'=>false]);
        $supplier->contacts()->create($data+['is_primary'=>$request->boolean('is_primary')]);
        return back()->with('success','Supplier contact added.');
    }

    public function destroySupplierContact(Supplier $supplier, \App\Models\SupplierContact $contact): RedirectResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404); $contact->delete(); return back()->with('success','Supplier contact removed.');
    }

    public function purchaseOrders(): View
    {
        $orders = PurchaseOrder::query()->with('supplier')->latest('order_date')->paginate(15);
        return view('procurement.purchase-orders.index', compact('orders'));
    }

    public function createPurchaseOrder(): View
    {
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name')->get(['id','name','code']);
        $variants = ProductVariant::query()->with('product')->orderBy('sku')->get();
        return view('procurement.purchase-orders.create', compact('suppliers','variants'));
    }

    public function goodsReceipts(): View
    {
        $receipts = GoodsReceipt::query()->with(['supplier', 'purchaseOrder'])->latest('received_at')->paginate(15);
        return view('procurement.goods-receipts.index', compact('receipts'));
    }

    public function createGoodsReceipt(): View
    {
        $orders = PurchaseOrder::query()->with(['supplier','items.productVariant.product'])->whereIn('status',['approved','partially_received'])->orderByDesc('order_date')->get();
        return view('procurement.goods-receipts.create', compact('orders'));
    }

    public function invoices(): View
    {
        $invoices = SupplierInvoice::query()->with('supplier')->latest('invoice_date')->paginate(15);
        return view('procurement.invoices.index', compact('invoices'));
    }

    public function createInvoice(): View
    {
        return view('procurement.invoices.create', ['suppliers'=>Supplier::where('status','active')->orderBy('name')->get(), 'orders'=>PurchaseOrder::with('supplier')->latest('order_date')->get(), 'receipts'=>GoodsReceipt::with('supplier')->where('status','confirmed')->latest('received_at')->get()]);
    }

    public function payments(): View
    {
        $payments = SupplierPayment::query()->with(['supplier', 'invoice'])->latest('payment_date')->paginate(15);
        return view('procurement.payments.index', compact('payments'));
    }

    public function createPayment(): View
    {
        return view('procurement.payments.create', ['invoices'=>SupplierInvoice::with('supplier')->where('remaining_amount','>',0)->latest('invoice_date')->get()]);
    }

    public function returns(): View
    {
        $returns = PurchaseReturn::query()->with('supplier')->latest('return_date')->paginate(15);
        return view('procurement.returns.index', compact('returns'));
    }

    public function createReturn(): View
    {
        return view('procurement.returns.create', ['suppliers'=>Supplier::where('status','active')->orderBy('name')->get(), 'variants'=>ProductVariant::with('product')->orderBy('sku')->get(), 'orders'=>PurchaseOrder::with('supplier')->latest('order_date')->get(), 'receipts'=>GoodsReceipt::with('supplier')->where('status','confirmed')->latest('received_at')->get()]);
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

    public function storePurchaseOrder(Request $request, CreatePurchaseOrderAction $action): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $data['discount'] = $data['discount'] ?? 0;
        $data['tax'] = $data['tax'] ?? 0;

        foreach ($data['items'] as &$item) {
            $item['discount'] = $item['discount'] ?? 0;
            $item['tax'] = $item['tax'] ?? 0;
        }

        return redirect()
            ->route('purchase-orders.show', $action->handle($data))
            ->with('success', 'Purchase order created.');
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('supplier', 'items.productVariant.product', 'receipts.items');
        return view('procurement.purchase-orders.show', compact('purchaseOrder'));
    }

    public function confirmReceipt(
        GoodsReceipt $goodsReceipt,
        ConfirmGoodsReceiptAction $action
    ): RedirectResponse {
        $action->handle($goodsReceipt);

        return back()->with('success', 'Goods receipt confirmed and inventory posted.');
    }

    public function storeGoodsReceipt(Request $request): RedirectResponse
    {
        $data=$request->validate(['purchase_order_id'=>'required|exists:purchase_orders,id','received_at'=>'required|date','notes'=>'nullable|string','items'=>'required|array|min:1','items.*.purchase_order_item_id'=>'required|exists:purchase_order_items,id','items.*.received_quantity'=>'required|numeric|gt:0','items.*.accepted_quantity'=>'required|numeric|min:0','items.*.rejected_quantity'=>'required|numeric|min:0','items.*.unit_cost'=>'required|numeric|min:0','items.*.notes'=>'nullable|string']);
        $receipt=\Illuminate\Support\Facades\DB::transaction(function()use($data){$po=PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($data['purchase_order_id']);abort_if($po->status==='cancelled',422,'Cancelled purchase orders cannot receive goods.');$receipt=GoodsReceipt::create(['receipt_number'=>app(CodeGeneratorService::class)->generate('GR',GoodsReceipt::max('id')+1),'purchase_order_id'=>$po->id,'supplier_id'=>$po->supplier_id,'received_at'=>$data['received_at'],'received_by'=>auth()->id(),'notes'=>$data['notes']??null]);foreach($data['items'] as $item){$poItem=$po->items->firstWhere('id',$item['purchase_order_item_id']);abort_unless($poItem && bccomp((string)$item['accepted_quantity'],bcsub((string)$item['received_quantity'],(string)$item['rejected_quantity'],3),3)===0,422);$receipt->items()->create($item+['product_variant_id'=>$poItem->product_variant_id,'ordered_quantity'=>$poItem->quantity]);}return $receipt;});return redirect()->route('goods-receipts.show',$receipt)->with('success','Goods receipt created.');
    }

    public function showGoodsReceipt(GoodsReceipt $goodsReceipt): View { $goodsReceipt->load(['supplier','purchaseOrder','items.productVariant.product']); return view('procurement.goods-receipts.show',compact('goodsReceipt')); }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $total = $data['subtotal'] - ($data['discount'] ?? 0) + ($data['tax'] ?? 0);
        abort_if($total < 0, 422, 'Invoice total cannot be negative.');
        foreach (['purchase_order_id'=>'purchase_orders','goods_receipt_id'=>'goods_receipts'] as $field=>$table) if (!empty($data[$field]) && (int) \Illuminate\Support\Facades\DB::table($table)->where('id',$data[$field])->value('supplier_id') !== (int)$data['supplier_id']) abort(422,'Linked document supplier must match invoice supplier.');

        $invoice = SupplierInvoice::create($data + [
            'invoice_number' => app(CodeGeneratorService::class)->generate('PINV', SupplierInvoice::max('id') + 1),
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'total' => $total,
            'remaining_amount' => $total,
            'status' => 'unpaid',
        ]);

        return back()->with('success', "Invoice {$invoice->invoice_number} created.");
    }

    public function storePayment(Request $request, RecordSupplierPaymentAction $action): RedirectResponse
    {
        $data = $request->validate([
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

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
