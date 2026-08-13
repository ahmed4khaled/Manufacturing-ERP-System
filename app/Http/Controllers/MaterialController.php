<?php

namespace App\Http\Controllers;

use App\Actions\Material\CreateMaterialAction;
use App\Actions\Material\DeleteMaterialAction;
use App\Actions\Material\UpdateMaterialAction;
use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:materials.view')->only(['index', 'show']);
    //     $this->middleware('permission:materials.create')->only(['create', 'store']);
    //     $this->middleware('permission:materials.edit')->only(['edit', 'update']);
    //     $this->middleware('permission:materials.delete')->only('destroy');
    // }

    public function index(Request $request): View
    {
        $materials = Material::query()
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterCategory($request->input('category'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('materials.index', compact('materials'));
    }

    public function create(): View
    {
        return view('materials.create');
    }

    public function store(
        StoreMaterialRequest $request,
        CreateMaterialAction $action,
    ): RedirectResponse {
        $material = $action->handle($request->validated());

        return redirect()
            ->route('materials.show', $material)
            ->with('success', 'Material created successfully.');
    }

    public function show(Material $material): View
    {
        return view('materials.show', compact('material'));
    }

    public function edit(Material $material): View
    {
        return view('materials.edit', compact('material'));
    }

    public function update(
        UpdateMaterialRequest $request,
        Material $material,
        UpdateMaterialAction $action,
    ): RedirectResponse {
        $action->handle($material, $request->validated());

        return redirect()
            ->route('materials.show', $material)
            ->with('success', 'Material updated successfully.');
    }

    public function destroy(
        Material $material,
        DeleteMaterialAction $action,
    ): RedirectResponse {
        $action->handle($material);

        return redirect()
            ->route('materials.index')
            ->with('success', 'Material deleted successfully.');
    }
}
