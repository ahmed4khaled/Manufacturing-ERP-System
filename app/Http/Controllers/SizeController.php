<?php

namespace App\Http\Controllers;

use App\Actions\Size\CreateSizeAction;
use App\Actions\Size\DeleteSizeAction;
use App\Actions\Size\UpdateSizeAction;
use App\Http\Requests\Size\StoreSizeRequest;
use App\Http\Requests\Size\UpdateSizeRequest;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SizeController extends Controller
{
    public function index(Request $request): View
    {
        $sizes = Size::query()
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('sizes.index', compact('sizes'));
    }

    public function create(): View
    {
        return view('sizes.create');
    }

    public function store(
        StoreSizeRequest $request,
        CreateSizeAction $action,
    ): RedirectResponse {
        $size = $action->handle($request->validated());

        return redirect()
            ->route('sizes.show', $size)
            ->with('success', 'Size created successfully.');
    }

    public function show(Size $size): View
    {
        $size->loadCount('variants');

        return view('sizes.show', compact('size'));
    }

    public function edit(Size $size): View
    {
        return view('sizes.edit', compact('size'));
    }

    public function update(
        UpdateSizeRequest $request,
        Size $size,
        UpdateSizeAction $action,
    ): RedirectResponse {
        $action->handle($size, $request->validated());

        return redirect()
            ->route('sizes.show', $size)
            ->with('success', 'Size updated successfully.');
    }

    public function destroy(
        Size $size,
        DeleteSizeAction $action,
    ): RedirectResponse {
        $action->handle($size);

        return redirect()
            ->route('sizes.index')
            ->with('success', 'Size deleted successfully.');
    }
}
