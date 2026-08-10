<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\CreateOpeningStockAction;
use App\Actions\Inventory\CreateStockAdjustmentAction;
use App\Enums\StockMovementDirection;
use App\Http\Requests\Inventory\StoreOpeningStockRequest;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $variants = ProductVariant::query()
            ->with(['product', 'color', 'size'])
            ->withSum(['stockMovements as stock_in_quantity' => fn ($query) => $query->where('direction', StockMovementDirection::IN)], 'quantity')
            ->withSum(['stockMovements as stock_out_quantity' => fn ($query) => $query->where('direction', StockMovementDirection::OUT)], 'quantity')
            ->search($request->input('search'))
            ->filterProduct($request->input('product_id'))
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $variants->getCollection()->each(function (ProductVariant $variant) {
            $variant->current_stock = (int) $variant->stock_in_quantity - (int) $variant->stock_out_quantity;
        });
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('inventory.index', compact('variants', 'products'));
    }

    public function movements(Request $request): View
    {
        $movements = StockMovement::query()->with(['productVariant.product', 'productVariant.color', 'productVariant.size'])->search($request->input('search'))->filterProduct($request->input('product_id'))->filterVariant($request->input('product_variant_id'))->filterMovementType($request->input('movement_type'))->filterDirection($request->input('direction'))->filterDateFrom($request->input('date_from'))->filterDateTo($request->input('date_to'))->orderByDesc('movement_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);
        $variants = ProductVariant::query()->with(['product', 'color', 'size'])->orderBy('sku')->get();

        return view('inventory.movements', compact('movements', 'products', 'variants'));
    }

    public function show(ProductVariant $productVariant, StockService $stockService): View
    {
        $productVariant->load(['product', 'color', 'size']);
        $movements = $stockService->attachRunningBalances($productVariant->stockMovements()->orderBy('movement_date')->orderBy('id')->paginate(15));
        $currentStock = $stockService->getCurrentStock($productVariant);

        return view('inventory.show', compact('productVariant', 'movements', 'currentStock'));
    }

    public function createOpeningStock(): View
    {
        $variants = ProductVariant::query()->with(['product', 'color', 'size'])->orderBy('sku')->get();

        return view('inventory.opening-stock', compact('variants'));
    }

    public function storeOpeningStock(StoreOpeningStockRequest $request, CreateOpeningStockAction $action): RedirectResponse
    {
        $movement = $action->handle($request->validated());

        return redirect()->route('inventory.show', $movement->product_variant_id)->with('success', 'Opening stock recorded successfully.');
    }

    public function createAdjustment(): View
    {
        $variants = ProductVariant::query()->with(['product', 'color', 'size'])->orderBy('sku')->get();

        return view('inventory.adjustments', compact('variants'));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request, CreateStockAdjustmentAction $action): RedirectResponse
    {
        $movement = $action->handle($request->validated());

        return redirect()->route('inventory.show', $movement->product_variant_id)->with('success', 'Stock adjustment recorded successfully.');
    }
}
