<?php

namespace App\Http\Controllers;

use App\Actions\MaterialInventory\CreateMaterialOpeningStockAction;
use App\Actions\MaterialInventory\CreateMaterialStockAdjustmentAction;
use App\Enums\StockMovementDirection;
use App\Http\Requests\MaterialInventory\StoreMaterialOpeningStockRequest;
use App\Http\Requests\MaterialInventory\StoreMaterialStockAdjustmentRequest;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Services\MaterialStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialInventoryController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('permission:material_inventory.view')->only(['index', 'show']);
    //     $this->middleware('permission:material_inventory.movements.view')->only('movements');
    //     $this->middleware('permission:material_inventory.opening_stock')->only(['createOpeningStock', 'storeOpeningStock']);
    //     $this->middleware('permission:material_inventory.adjustment')->only(['createAdjustment', 'storeAdjustment']);
    // }
    public function index(Request $request, MaterialStockService $materialStockService): View
    {
        $materials = Material::query()
            ->withSum(['stockMovements as stock_in_quantity' => fn ($query) => $query->where('direction', StockMovementDirection::IN)], 'quantity')
            ->withSum(['stockMovements as stock_out_quantity' => fn ($query) => $query->where('direction', StockMovementDirection::OUT)], 'quantity')
            ->search($request->input('search'))
            ->filterCategory($request->input('category'))
            ->filterStatus($request->input('status'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $materials->getCollection()->each(function (Material $material) {
            $stockIn = (string) ($material->stock_in_quantity ?? 0);
            $stockOut = (string) ($material->stock_out_quantity ?? 0);
            $material->current_stock = bcsub($stockIn, $stockOut, 3);
        });

        return view('material-inventory.index', compact('materials'));
    }

    public function movements(Request $request): View
    {
        $movements = MaterialStockMovement::query()
            ->with(['material', 'user'])
            ->search($request->input('search'))
            ->filterMaterial($request->input('material_id'))
            ->filterMovementType($request->input('movement_type'))
            ->filterDirection($request->input('direction'))
            ->filterDateFrom($request->input('date_from'))
            ->filterDateTo($request->input('date_to'))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $materials = Material::query()->orderBy('name')->get();

        return view('material-inventory.movements', compact('movements', 'materials'));
    }

    public function show(Material $material, MaterialStockService $materialStockService): View
    {
        $movements = $materialStockService->attachRunningBalances(
            $material->stockMovements()->orderBy('movement_date')->orderBy('id')->paginate(15)
        );
        $currentStock = $materialStockService->getCurrentStock($material);

        return view('material-inventory.show', compact('material', 'movements', 'currentStock'));
    }

    public function createOpeningStock(): View
    {
        $materials = Material::query()->orderBy('name')->get();

        return view('material-inventory.opening-stock', compact('materials'));
    }

    public function storeOpeningStock(
        StoreMaterialOpeningStockRequest $request,
        CreateMaterialOpeningStockAction $action,
    ): RedirectResponse {
        $movement = $action->handle($request->validated());

        return redirect()
            ->route('material-inventory.show', $movement->material_id)
            ->with('success', 'Opening stock recorded successfully.');
    }

    public function createAdjustment(): View
    {
        $materials = Material::query()->orderBy('name')->get();

        return view('material-inventory.adjustments', compact('materials'));
    }

    public function storeAdjustment(
        StoreMaterialStockAdjustmentRequest $request,
        CreateMaterialStockAdjustmentAction $action,
    ): RedirectResponse {
        $movement = $action->handle($request->validated());

        return redirect()
            ->route('material-inventory.show', $movement->material_id)
            ->with('success', 'Stock adjustment recorded successfully.');
    }
}
