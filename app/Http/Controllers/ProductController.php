<?php

namespace App\Http\Controllers;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterCategory($request->input('category_id'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = Category::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('products.create', compact('categories'));
    }

    public function store(
        StoreProductRequest $request,
        CreateProductAction $action,
    ): RedirectResponse {
        $product = $action->handle($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'تم إنشاء الموديل بنجاح.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'variants.color', 'variants.size']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $categories = Category::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProductAction $action,
    ): RedirectResponse {
        $action->handle($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'تم تحديث الموديل بنجاح.');
    }

    public function destroy(
        Product $product,
        DeleteProductAction $action,
    ): RedirectResponse {
        $action->handle($product);

        return redirect()
            ->route('products.index')
            ->with('success', 'تم حذف الموديل بنجاح.');
    }
}
