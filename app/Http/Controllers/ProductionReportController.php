<?php

namespace App\Http\Controllers;

use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Models\ProductionOrderMaterial;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReportController extends Controller
{
    public function materialVariance(Request $request): View
    {
        $rows = ProductionOrderMaterial::query()->with(['productionOrder', 'material'])->whereHas('productionOrder', fn ($q) => $q->when($request->input('date_from'), fn ($x, $date) => $x->whereDate('planned_start_date', '>=', $date))->when($request->input('date_to'), fn ($x, $date) => $x->whereDate('planned_start_date', '<=', $date)))->latest()->paginate(25)->withQueryString();
        $rows->getCollection()->each(function ($row) {
            $query = $row->productionOrder->materialTransactions()->where('material_id', $row->material_id)->where('status', '!=', ProductionMaterialTransactionStatus::REJECTED);
            $row->actual_consumption = (string) (clone $query)->where('type', ProductionMaterialTransactionType::USAGE)->sum('quantity');
            $row->returned_quantity = (string) (clone $query)->where('type', ProductionMaterialTransactionType::RETURN)->sum('quantity');
            $row->waste_quantity = (string) (clone $query)->where('type', ProductionMaterialTransactionType::WASTE)->sum('quantity');
            $row->variance = bcsub(bcadd($row->actual_consumption, $row->waste_quantity, 3), (string) $row->standard_quantity, 3);
        });

        return view('production-reports.material-variance', compact('rows'));
    }
}
