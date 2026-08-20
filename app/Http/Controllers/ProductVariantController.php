<?php

namespace App\Http\Controllers;

use App\Actions\ProductVariant\CreateProductVariantAction;
use App\Actions\ProductVariant\DeleteProductVariantAction;
use App\Actions\ProductVariant\UpdateProductVariantAction;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    public function index(Request $request): View
    {
        $variants = ProductVariant::query()
            ->with(['product', 'color', 'size'])
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterProduct($request->input('product_id'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('product-variants.index', compact('variants', 'products'));
    }

    public function create(): View
    {
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);
        $colors = Color::query()->orderBy('name')->get(['id', 'name', 'code', 'hex_code']);
        $sizes = Size::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('product-variants.create', compact('products', 'colors', 'sizes'));
    }

    public function store(
        StoreProductVariantRequest $request,
        CreateProductVariantAction $action,
    ): RedirectResponse {
        $variant = $action->handle($request->validated());

        return redirect()
            ->route('product-variants.show', $variant)
            ->with('success', 'تم إنشاء متغير المنتج بنجاح.');
    }

    public function show(ProductVariant $productVariant): View
    {
        $productVariant->load(['product', 'color', 'size']);

        return view('product-variants.show', compact('productVariant'));
    }

    public function edit(ProductVariant $productVariant): View
    {
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'code']);
        $colors = Color::query()->orderBy('name')->get(['id', 'name', 'code', 'hex_code']);
        $sizes = Size::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('product-variants.edit', compact('productVariant', 'products', 'colors', 'sizes'));
    }

    public function update(
        UpdateProductVariantRequest $request,
        ProductVariant $productVariant,
        UpdateProductVariantAction $action,
    ): RedirectResponse {
        $action->handle($productVariant, $request->validated());

        return redirect()
            ->route('product-variants.show', $productVariant)
            ->with('success', 'تم تحديث متغير المنتج بنجاح.');
    }

    public function destroy(
        ProductVariant $productVariant,
        DeleteProductVariantAction $action,
    ): RedirectResponse {
        $action->handle($productVariant);

        return redirect()
            ->route('product-variants.index')
            ->with('success', 'تم حذف متغير المنتج بنجاح.');
    }
}
