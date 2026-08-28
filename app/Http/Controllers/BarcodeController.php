<?php

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\ProductVariant;
use App\Services\BarcodeLabelService;
use App\Services\ProductionSummaryService;
use App\Services\ProductVariantLookupService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BarcodeController extends Controller
{
    public function lookup(
        Request $request,
        ProductVariantLookupService $lookup,
        StockService $stock,
    ): JsonResponse {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:100'],
            'context' => ['required', 'in:sales_invoice,sales_return,inventory'],
        ]);

        $permissions = match ($validated['context']) {
            'sales_invoice', 'sales_return' => ['sales_invoices.create', 'sales_invoices.edit'],
            'inventory' => ['inventory.view', 'inventory.opening_stock', 'inventory.adjustment'],
        };
        abort_unless($request->user()?->hasAnyPermission($permissions), 403);

        $variant = $lookup->byBarcode($validated['barcode']);

        return response()->json([
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'product_code' => $variant->product?->code,
            'product_variant_id' => $variant->id,
            'color' => $variant->color?->name ?? '—',
            'size' => $variant->size?->name ?? '—',
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'unit_price' => (float) $variant->default_price,
            'available_stock' => $stock->getCurrentStock($variant),
        ]);
    }

    public function variantLabels(
        Request $request,
        ProductVariant $productVariant,
        BarcodeLabelService $labels,
    ): View {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'mode' => ['nullable', 'in:preview,print'],
        ]);

        return $this->labelView(
            $labels->forVariant($productVariant, (int) $validated['quantity']),
            'ملصقات '.$productVariant->sku,
            ($validated['mode'] ?? 'preview') === 'print',
        );
    }

    public function productionLabels(
        Request $request,
        ProductionOrder $productionOrder,
        ProductionSummaryService $summary,
        BarcodeLabelService $labels,
    ): View {
        abort_unless($productionOrder->status === ProductionOrderStatus::COMPLETED, 404);

        $goodOutput = (int) floor((float) $summary->orderSummary($productionOrder)['good']);
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.min(1000, max(1, $goodOutput))],
            'mode' => ['nullable', 'in:preview,print'],
        ]);
        $quantity = isset($validated['quantity']) ? (int) $validated['quantity'] : $goodOutput;

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'لا يوجد إنتاج جيد يمكن طباعة ملصقات له.']);
        }

        return $this->labelView(
            $labels->forVariant($productionOrder->productVariant, $quantity),
            'ملصقات '.$productionOrder->order_number,
            ($validated['mode'] ?? 'preview') === 'print',
        );
    }

    public function batch(): View
    {
        $outputs = ProductionOutput::query()
            ->with(['productionOrder', 'productVariant.product', 'productVariant.color', 'productVariant.size'])
            ->whereHas('productionOrder', fn ($query) => $query->where('status', ProductionOrderStatus::COMPLETED))
            ->latest('output_date')
            ->latest('id')
            ->limit(200)
            ->get();

        return view('barcodes.batch', compact('outputs'));
    }

    public function batchLabels(Request $request, BarcodeLabelService $labels): View
    {
        $validated = $request->validate([
            'outputs' => ['required', 'array', 'min:1', 'max:100'],
            'outputs.*' => ['required', 'integer', 'min:1', 'max:1000'],
            'mode' => ['nullable', 'in:preview,print'],
        ]);
        $requested = collect($validated['outputs']);
        $outputs = ProductionOutput::query()
            ->with(['productionOrder', 'productVariant.product', 'productVariant.color', 'productVariant.size'])
            ->whereIn('id', $requested->keys())
            ->whereHas('productionOrder', fn ($query) => $query->where('status', ProductionOrderStatus::COMPLETED))
            ->get()
            ->keyBy('id');

        if ($outputs->count() !== $requested->count()) {
            throw ValidationException::withMessages(['outputs' => 'تحتوي الدفعة على مخرجات إنتاج غير صالحة للطباعة.']);
        }

        $allLabels = collect();
        foreach ($requested as $outputId => $quantity) {
            $output = $outputs->get((int) $outputId);
            if ((int) $quantity > (int) floor((float) $output->quantity)) {
                throw ValidationException::withMessages([
                    "outputs.{$outputId}" => 'كمية الملصقات لا يمكن أن تتجاوز كمية مخرج الإنتاج.',
                ]);
            }
            $allLabels->push(...$labels->forVariant($output->productVariant, (int) $quantity));
        }

        if ($allLabels->count() > 2000) {
            throw ValidationException::withMessages(['outputs' => 'الحد الأقصى للدفعة الواحدة هو 2000 ملصق.']);
        }

        return $this->labelView(
            $allLabels,
            'دفعة ملصقات الإنتاج',
            ($validated['mode'] ?? 'preview') === 'print',
        );
    }

    /** @param Collection<int, array<string, string>>|array<int, array<string, string>> $labels */
    private function labelView(Collection|array $labels, string $title, bool $autoPrint): View
    {
        return view('barcodes.labels', [
            'labels' => collect($labels),
            'title' => $title,
            'autoPrint' => $autoPrint,
        ]);
    }
}
