<?php

namespace App\Http\Controllers;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(
        StoreCategoryRequest $request,
        CreateCategoryAction $action,
    ): RedirectResponse {
        $category = $action->handle($request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category): View
    {
        $category->loadCount('products');

        return view('categories.show', compact('category'));
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        UpdateCategoryAction $action,
    ): RedirectResponse {
        $action->handle($category, $request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(
        Category $category,
        DeleteCategoryAction $action,
    ): RedirectResponse {
        $action->handle($category);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
