<?php

namespace App\Http\Controllers;

use App\Actions\SalesInvoice\CreateSalesInvoiceAction;
use App\Actions\SalesInvoice\DeleteSalesInvoiceAction;
use App\Actions\SalesInvoice\UpdateSalesInvoiceAction;
use App\Enums\SalesInvoiceStatus;
use App\Http\Requests\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\SalesInvoice\UpdateSalesInvoiceRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = SalesInvoice::query()
            ->with('customer')
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
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);
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
            ->with('success', 'Sales invoice created successfully.');
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['customer', 'items']);

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

        return view('sales-invoices.show', compact('salesInvoice', 'groupedItems'));
    }

    public function edit(SalesInvoice $salesInvoice): View|RedirectResponse
    {
        if ($salesInvoice->status !== SalesInvoiceStatus::DRAFT) {
            return redirect()
                ->route('sales-invoices.show', $salesInvoice)
                ->with('error', 'Only draft invoices can be edited.');
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
            ->with('success', 'Sales invoice updated successfully.');
    }

    public function destroy(
        SalesInvoice $salesInvoice,
        DeleteSalesInvoiceAction $action,
    ): RedirectResponse {
        $action->handle($salesInvoice);

        return redirect()
            ->route('sales-invoices.index')
            ->with('success', 'Sales invoice deleted successfully.');
    }

    public function productVariants(Product $product): JsonResponse
    {
        $variants = $product->variants()
            ->with(['color', 'size'])
            ->orderBy('id')
            ->get()
            ->map(fn ($variant) => [
                'product_variant_id' => $variant->id,
                'color' => $variant->color?->name ?? '—',
                'size' => $variant->size?->name ?? '—',
                'unit_price' => (float) $variant->default_price,
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
