<?php

namespace App\Http\Controllers;

use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCostLayerController extends Controller
{
    public function index(Request $request): View
    {
        $layers = ProductCostLayer::query()
            ->with(['productVariant.product', 'productVariant.color', 'productVariant.size', 'productionOrder', 'productionOutput'])
            ->when($request->filled('product_variant_id'), fn ($query) => $query->where('product_variant_id', $request->integer('product_variant_id')))
            ->when($request->input('status') === 'available', fn ($query) => $query->where('is_final', true)->whereNotNull('unit_cost')->where('remaining_quantity', '>', 0))
            ->when($request->input('status') === 'depleted', fn ($query) => $query->where('is_final', true)->whereNotNull('unit_cost')->where('remaining_quantity', '<=', 0))
            ->when($request->input('status') === 'unvalued', fn ($query) => $query->where('is_final', true)->whereNull('unit_cost'))
            ->when($request->input('status') === 'provisional', fn ($query) => $query->where('is_final', false))
            ->orderByDesc('layer_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $variants = ProductVariant::query()
            ->with(['product', 'color', 'size'])
            ->orderBy('sku')
            ->get();

        return view('cost-layers.products.index', compact('layers', 'variants'));
    }
}
