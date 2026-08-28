<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialCostLayer;
use App\Models\ProductionMaterialTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialCostLayerController extends Controller
{
    public function index(Request $request): View
    {
        $layers = MaterialCostLayer::query()
            ->with(['material', 'goodsReceipt'])
            ->when($request->filled('material_id'), fn ($query) => $query->where('material_id', $request->integer('material_id')))
            ->when($request->input('status') === 'available', fn ($query) => $query->whereNotNull('unit_cost')->where('remaining_quantity', '>', 0))
            ->when($request->input('status') === 'depleted', fn ($query) => $query->whereNotNull('unit_cost')->where('remaining_quantity', '<=', 0))
            ->when($request->input('status') === 'unvalued', fn ($query) => $query->whereNull('unit_cost'))
            ->orderByDesc('layer_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $materials = Material::query()->orderBy('name')->get(['id', 'code', 'name']);

        return view('cost-layers.materials.index', compact('layers', 'materials'));
    }

    public function show(MaterialCostLayer $materialCostLayer): View
    {
        $materialCostLayer->load(['material', 'goodsReceipt', 'stockMovement']);

        $allocations = $materialCostLayer->allocations()
            ->where('reference_type', ProductionMaterialTransaction::class)
            ->with('stockMovement')
            ->orderBy('id')
            ->get();

        $transactions = ProductionMaterialTransaction::query()
            ->with(['productionOrder', 'workAssignment.operation', 'worker'])
            ->whereIn('id', $allocations->pluck('reference_id'))
            ->get()
            ->keyBy('id');

        return view('cost-layers.materials.show', compact('materialCostLayer', 'allocations', 'transactions'));
    }
}
