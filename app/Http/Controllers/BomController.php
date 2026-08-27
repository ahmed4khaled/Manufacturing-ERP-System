<?php

namespace App\Http\Controllers;

use App\Actions\Bom\ActivateBomAction;
use App\Actions\Bom\CreateBomAction;
use App\Actions\Bom\UpdateBomAction;
use App\Enums\ManufacturingOperationStatus;
use App\Enums\MaterialStatus;
use App\Http\Requests\Bom\StoreBomRequest;
use App\Http\Requests\Bom\UpdateBomRequest;
use App\Models\Bom;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BomController extends Controller
{
    public function index(Request $request): View
    {
        $boms = Bom::query()->with(['product', 'productVariant.color', 'productVariant.size'])->search($request->input('search'))->filterStatus($request->input('status'))->latest()->paginate(15)->withQueryString();

        return view('boms.index', compact('boms'));
    }

    public function create(): View
    {
        return view('boms.create', $this->formData());
    }

    public function store(StoreBomRequest $request, CreateBomAction $action): RedirectResponse
    {
        $bom = $action->handle($request->validated());

        return redirect()->route('boms.show', $bom)->with('success', 'تم إنشاء BOM بنجاح.');
    }

    public function show(Bom $bom): View
    {
        $bom->load(['product', 'productVariant.color', 'productVariant.size', 'items.material', 'items.operationAllocations.bomOperation', 'operations.manufacturingOperation', 'creator', 'activator']);

        return view('boms.show', compact('bom'));
    }

    public function edit(Bom $bom): View
    {
        $bom->load(['items.operationAllocations.bomOperation', 'operations']);

        return view('boms.edit', ['bom' => $bom] + $this->formData());
    }

    public function update(UpdateBomRequest $request, Bom $bom, UpdateBomAction $action): RedirectResponse
    {
        $updated = $action->handle($bom, $request->validated());

        return redirect()->route('boms.show', $updated)->with('success', $updated->id === $bom->id ? 'تم تحديث BOM.' : 'تم إنشاء إصدار BOM جديد كمسودة.');
    }

    public function activate(Bom $bom, ActivateBomAction $action): RedirectResponse
    {
        try {
            $action->handle($bom);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'تم تفعيل إصدار BOM.');
    }

    private function formData(): array
    {
        return ['products' => Product::query()->with(['variants.color', 'variants.size'])->orderBy('name')->get(), 'materials' => Material::query()->where('status', MaterialStatus::ACTIVE)->orderBy('name')->get(), 'operations' => ManufacturingOperation::query()->where('status', ManufacturingOperationStatus::ACTIVE)->orderBy('name')->get()];
    }
}
