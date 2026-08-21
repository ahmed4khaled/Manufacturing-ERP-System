<?php

namespace App\Http\Controllers;

use App\Actions\ManufacturingOperation\SaveManufacturingOperationAction;
use App\Http\Requests\ManufacturingOperation\StoreManufacturingOperationRequest;
use App\Models\ManufacturingOperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManufacturingOperationController extends Controller
{
    public function index(Request $request): View
    {
        $operations = ManufacturingOperation::query()->search($request->input('search'))->filterStatus($request->input('status'))->orderBy('name')->paginate(15)->withQueryString();

        return view('manufacturing-operations.index', compact('operations'));
    }

    public function create(): View
    {
        return view('manufacturing-operations.create');
    }

    public function store(StoreManufacturingOperationRequest $request, SaveManufacturingOperationAction $action): RedirectResponse
    {
        $action->handle($request->validated());

        return redirect()->route('manufacturing-operations.index')->with('success', 'تم إنشاء العملية بنجاح.');
    }

    public function edit(ManufacturingOperation $manufacturingOperation): View
    {
        return view('manufacturing-operations.edit', compact('manufacturingOperation'));
    }

    public function update(StoreManufacturingOperationRequest $request, ManufacturingOperation $manufacturingOperation, SaveManufacturingOperationAction $action): RedirectResponse
    {
        $action->handle($request->validated(), $manufacturingOperation);

        return redirect()->route('manufacturing-operations.index')->with('success', 'تم تحديث العملية بنجاح.');
    }
}
