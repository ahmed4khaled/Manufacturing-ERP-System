<?php

namespace App\Http\Controllers;

use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesInvoice\CreateSalesInvoiceAction;
use App\Actions\SalesInvoice\DeleteSalesInvoiceAction;
use App\Actions\SalesInvoice\UpdateSalesInvoiceAction;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Http\Requests\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\SalesInvoice\UpdateSalesInvoiceRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCostAllocation;
use App\Models\SalesInvoice;
use App\Services\PaymentReceiptAllocationService;
use App\Services\Printing\DocumentPrinter;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function print(
        SalesInvoice $salesInvoice,
        DocumentPrinter $printer,
        PaymentReceiptAllocationService $allocationService,
    ): View {
        $salesInvoice->load([
            'customer',
            'items',
        ]);
        $paidAmount = $allocationService->paidAmountForInvoice($salesInvoice->id);
        $returnedAmount = $salesInvoice->salesReturns()
            ->where('status', SalesReturnStatus::CONFIRMED)
            ->sum('total_amount');
        $invoiceTotal = (float) $salesInvoice->total_amount;
        $remainingAmount = max(
            0,
            $invoiceTotal - (float) $returnedAmount - (float) $paidAmount
        );

        return $printer->render(
            $salesInvoice,
            'sales_invoice',
            [
                'paidAmount' => (float) $paidAmount,
                'returnedAmount' => (float) $returnedAmount,
                'remainingAmount' => $remainingAmount,
            ]
        );
    }

    public function index(Request $request): View
    {
        $invoices = SalesInvoice::query()
            ->with('customer')
            ->withSum([
                'salesReturns as returned_amount' => fn ($query) => $query
                    ->where('status', SalesReturnStatus::CONFIRMED),
            ], 'total_amount')
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterCustomer($request->input('customer_id'))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('sales-invoices.index', compact('invoices', 'customers'));
    }

    public function create(): View
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code', 'credit_limit']);
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('sales-invoices.create', compact('customers', 'products'));
    }

    public function store(
        StoreSalesInvoiceRequest $request,
        CreateSalesInvoiceAction $action,
    ): RedirectResponse {
        $invoice = $action->handle($request->validated());

        return redirect()
            ->route('sales-invoices.show', $invoice)
            ->with('success', 'تم إنشاء فاتورة المبيعات بنجاح.');
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer',
            'items',
        ]);

        $salesInvoice->loadSum([
            'salesReturns as returned_amount' => fn ($query) => $query
                ->where('status', SalesReturnStatus::CONFIRMED),
        ], 'total_amount');

        $groupedItems = $salesInvoice->items
            ->groupBy('product_name')
            ->map(function ($items) {
                return [
                    'product_name' => $items->first()->product_name,
                    'total_quantity' => $items->sum('quantity'),
                    'total_amount' => $items->sum('line_total'),
                    'items' => $items,
                ];
            })
            ->values();

        $costAllocations = ProductCostAllocation::query()
            ->where('reference_type', SalesInvoice::class)
            ->where('reference_id', $salesInvoice->id)
            ->where('direction', 'out')
            ->with(['layer.productVariant.product', 'layer.productVariant.color', 'layer.productVariant.size'])
            ->orderBy('id')
            ->get();

        return view('sales-invoices.show', compact('salesInvoice', 'groupedItems', 'costAllocations'));
    }

    public function edit(SalesInvoice $salesInvoice): View|RedirectResponse
    {
        if ($salesInvoice->status !== SalesInvoiceStatus::DRAFT) {
            return redirect()
                ->route('sales-invoices.show', $salesInvoice)
                ->with('error', 'لا يمكن تعديل إلا الفواتير المسودة.');
        }

        $salesInvoice->load(['items.productVariant']);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);

        $existingProducts = $salesInvoice->items
            ->groupBy('product_name')
            ->map(function ($items) {
                $firstItem = $items->first();

                return [
                    'product_id' => $firstItem->productVariant?->product_id,
                    'product_name' => $firstItem->product_name,
                    'product_code' => $firstItem->productVariant?->product?->code,
                    'variants' => $items->map(fn ($item) => [
                        'product_variant_id' => $item->product_variant_id,
                        'color' => $item->color_name,
                        'size' => $item->size_name,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                    ])->values()->all(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_amount' => (float) $items->sum('line_total'),
                ];
            })
            ->values()
            ->all();

        return view('sales-invoices.edit', compact('salesInvoice', 'customers', 'products', 'existingProducts'));
    }

    public function update(
        UpdateSalesInvoiceRequest $request,
        SalesInvoice $salesInvoice,
        UpdateSalesInvoiceAction $action,
    ): RedirectResponse {
        $action->handle($salesInvoice, $request->validated());

        return redirect()
            ->route('sales-invoices.show', $salesInvoice)
            ->with('success', 'تم تحديث فاتورة المبيعات بنجاح.');
    }

    public function destroy(
        SalesInvoice $salesInvoice,
        DeleteSalesInvoiceAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesInvoice);
        } catch (ValidationException $e) {
            return redirect()
                ->route('sales-invoices.show', $salesInvoice)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('sales-invoices.index')
            ->with('success', 'تم حذف فاتورة المبيعات بنجاح.');
    }

    public function confirm(
        SalesInvoice $salesInvoice,
        ConfirmSalesInvoiceAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesInvoice);
        } catch (ValidationException $e) {
            return redirect()
                ->route('sales-invoices.show', $salesInvoice)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('sales-invoices.show', $salesInvoice)
            ->with('success', 'تم تأكيد فاتورة المبيعات بنجاح.');
    }

    public function cancel(
        SalesInvoice $salesInvoice,
        CancelSalesInvoiceAction $action,
    ): RedirectResponse {
        try {
            $action->handle($salesInvoice);
        } catch (ValidationException $e) {
            return redirect()
                ->route('sales-invoices.show', $salesInvoice)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('sales-invoices.show', $salesInvoice)
            ->with('success', 'تم إلغاء فاتورة المبيعات بنجاح.');
    }

    public function productVariants(Product $product, StockService $stockService): JsonResponse
    {
        $variants = $product->variants()
            ->with(['color', 'size'])
            ->orderBy('id')
            ->get();

        $currentStocks = $stockService->getCurrentStocks($variants);

        $variants = $variants->map(fn ($variant) => [
            'product_variant_id' => $variant->id,
            'color' => $variant->color?->name ?? '—',
            'size' => $variant->size?->name ?? '—',
            'unit_price' => (float) $variant->default_price,
            'available_stock' => $currentStocks->get($variant->id, 0),
            'quantity' => 0,
        ]);

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'variants' => $variants,
        ]);
    }
}
