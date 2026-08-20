<?php

namespace App\Http\Controllers;

use App\Actions\Color\CreateColorAction;
use App\Actions\Color\DeleteColorAction;
use App\Actions\Color\UpdateColorAction;
use App\Http\Requests\Color\StoreColorRequest;
use App\Http\Requests\Color\UpdateColorRequest;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ColorController extends Controller
{
    public function index(Request $request): View
    {
        $colors = Color::query()
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('colors.index', compact('colors'));
    }

    public function create(): View
    {
        return view('colors.create');
    }

    public function store(
        StoreColorRequest $request,
        CreateColorAction $action,
    ): RedirectResponse {
        $color = $action->handle($request->validated());

        return redirect()
            ->route('colors.show', $color)
            ->with('success', 'تم إنشاء اللون بنجاح.');
    }

    public function show(Color $color): View
    {
        $color->loadCount('variants');

        return view('colors.show', compact('color'));
    }

    public function edit(Color $color): View
    {
        return view('colors.edit', compact('color'));
    }

    public function update(
        UpdateColorRequest $request,
        Color $color,
        UpdateColorAction $action,
    ): RedirectResponse {
        $action->handle($color, $request->validated());

        return redirect()
            ->route('colors.show', $color)
            ->with('success', 'تم تحديث اللون بنجاح.');
    }

    public function destroy(
        Color $color,
        DeleteColorAction $action,
    ): RedirectResponse {
        $action->handle($color);

        return redirect()
            ->route('colors.index')
            ->with('success', 'تم حذف اللون بنجاح.');
    }
}
