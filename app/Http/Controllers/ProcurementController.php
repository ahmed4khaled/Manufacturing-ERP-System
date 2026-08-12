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
use App\Services\CodeGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcurementController extends Controller
{
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

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        return response()->json(
            $purchaseOrder->load('supplier', 'items.productVariant', 'receipts.items')
        );
    }

    public function confirmReceipt(
        GoodsReceipt $goodsReceipt,
        ConfirmGoodsReceiptAction $action
    ): RedirectResponse {
        $action->handle($goodsReceipt);

        return back()->with('success', 'Goods receipt confirmed and inventory posted.');
    }

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
