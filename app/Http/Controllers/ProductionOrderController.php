<?php

namespace App\Http\Controllers;

use App\Actions\ProductionOrder\CancelProductionOrderAction;
use App\Actions\ProductionOrder\CompleteProductionOrderAction;
use App\Actions\ProductionOrder\ConfirmProductionOrderAction;
use App\Actions\ProductionOrder\CreateProductionOrderAction;
use App\Actions\ProductionOrder\DeleteProductionOrderAction;
use App\Actions\ProductionOrder\HoldProductionOrderAction;
use App\Actions\ProductionOrder\RecordProductionOutputAction;
use App\Actions\ProductionOrder\ResumeProductionOrderAction;
use App\Enums\BomStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\WorkerStatus;
use App\Http\Requests\ProductionOrder\RecordProductionOutputRequest;
use App\Http\Requests\ProductionOrder\StoreProductionOrderRequest;
use App\Models\Bom;
use App\Models\MaterialCostAllocation;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\Worker;
use App\Services\InventoryCostingService;
use App\Services\MaterialStockService;
use App\Services\Printing\DocumentPrinter;
use App\Services\ProductionSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = ProductionOrder::query()->with(['productVariant.color', 'productVariant.size'])->withSum('outputs', 'quantity')->search($request->input('search'))->filterStatus($request->input('status'))->latest('planned_start_date')->latest('id')->paginate(15)->withQueryString();

        return view('production-orders.index', compact('orders'));
    }

    public function create(MaterialStockService $stock): View
    {
        $boms = Bom::query()->where('status', BomStatus::ACTIVE)->with(['product', 'productVariant.color', 'productVariant.size', 'items.material', 'items.operationAllocations', 'operations'])->orderBy('bom_number')->get();
        $stocks = $stock->getCurrentStocks($boms->flatMap->items->pluck('material')->filter()->unique('id')->values());
        $workers = Worker::query()->where('status', WorkerStatus::ACTIVE)->orderBy('name')->get();

        $bomWizardData = $boms->map(function (Bom $bom) use ($stocks): array {
            $variant = $bom->productVariant;
            $variantParts = array_values(array_filter([
                $variant?->color?->name,
                $variant?->size?->name,
            ]));
            $variantLabel = implode(' / ', $variantParts);

            if (filled($variant?->sku)) {
                $variantLabel = trim($variantLabel.' ('.$variant->sku.')');
            }

            return [
                'id' => $bom->id,
                'number' => $bom->bom_number,
                'version' => $bom->version,
                'product_id' => $bom->product_id,
                'product' => $bom->product?->name ?? '',
                'variant_id' => $bom->product_variant_id,
                'variant' => $variantLabel,
                'basis' => (float) $bom->basis_quantity,
                'items' => $bom->items->map(function ($item) use ($bom, $stocks): array {
                    $operationAllocations = $item->operationAllocations
                        ->sortBy(fn ($allocation) => $bom->operations->firstWhere('id', $allocation->bom_operation_id)?->sequence)
                        ->map(fn ($allocation): array => [
                            'bom_operation_id' => $allocation->bom_operation_id,
                            'quantity' => (float) $allocation->quantity,
                        ]);

                    if ($operationAllocations->isEmpty() && $bom->operations->isNotEmpty()) {
                        $operationAllocations->push([
                            'bom_operation_id' => $bom->operations->sortBy('sequence')->first()->id,
                            'quantity' => (float) $item->quantity,
                        ]);
                    }

                    return [
                        'id' => $item->id,
                        'name' => $item->material_name,
                        'unit' => $item->unit,
                        'quantity' => (float) $item->quantity,
                        'available' => (float) ($stocks[$item->material_id] ?? 0),
                        'operations' => $operationAllocations->values()->all(),
                    ];
                })->values()->all(),
                'operations' => $bom->operations->map(fn ($operation): array => [
                    'id' => $operation->id,
                    'name' => $operation->operation_name,
                    'sequence' => $operation->sequence,
                    'unit_cost' => (float) $operation->unit_cost,
                ])->values()->all(),
            ];
        })->values()->all();

        $workerWizardData = $workers->map(fn (Worker $worker): array => [
            'id' => $worker->id,
            'name' => $worker->name,
            'code' => $worker->code,
        ])->values()->all();

        return view('production-orders.create', compact(
            'boms',
            'stocks',
            'workers',
            'bomWizardData',
            'workerWizardData',
        ));
    }

    public function store(StoreProductionOrderRequest $request, CreateProductionOrderAction $action): RedirectResponse
    {
        $order = $action->handle($request->validated());

        return redirect()->route('production-orders.show', $order)->with('success', 'تم إنشاء أمر الإنتاج بنجاح.');
    }

    public function show(ProductionOrder $productionOrder, ProductionSummaryService $summary, InventoryCostingService $costing): View
    {
        $productionOrder->load(['creator', 'materials.material', 'materials.operationRequirements.productionOrderOperation', 'operations', 'workAssignments.worker', 'workAssignments.operation', 'workAssignments.materials.productionOrderMaterial', 'workAssignments.materials.operationRequirement', 'materialTransactions.worker', 'materialTransactions.material', 'materialTransactions.recorder', 'materialTransactions.reviewer', 'outputs.recorder', 'defects.worker', 'defects.operation', 'defects.recorder', 'defects.reviewer', 'reworks.worker', 'reworks.operation', 'activities.user']);
        $orderSummary = $summary->orderSummary($productionOrder);
        foreach ($productionOrder->workAssignments as $assignment) {
            foreach ($assignment->materials as $allocation) {
                $allocation->accountability = $summary->materialTotals($allocation);
            }
        }
        $workers = Worker::query()->where('status', WorkerStatus::ACTIVE)->orderBy('name')->get();

        $materialCostAllocations = MaterialCostAllocation::query()
            ->where('reference_type', ProductionMaterialTransaction::class)
            ->whereIn('reference_id', $productionOrder->materialTransactions->pluck('id'))
            ->with(['layer.material', 'stockMovement'])
            ->orderBy('id')
            ->get();

        $usesCurrentMaterialCost = in_array($productionOrder->status, [
            ProductionOrderStatus::CONFIRMED,
            ProductionOrderStatus::IN_PROGRESS,
            ProductionOrderStatus::PARTIALLY_COMPLETED,
        ], true);
        $displayMaterialCost = $usesCurrentMaterialCost
            ? $costing->currentProductionMaterialCost($productionOrder)
            : $productionOrder->actual_material_cost;
        $materialCostLabel = $productionOrder->status === ProductionOrderStatus::COMPLETED
            ? 'تكلفة خامات FIFO الفعلية النهائية'
            : 'تكلفة خامات FIFO الحالية';
        $materialVariance = $displayMaterialCost === null
            ? null
            : (float) $displayMaterialCost - (float) $productionOrder->standard_material_cost;

        return view('production-orders.show', compact('productionOrder', 'orderSummary', 'workers', 'materialCostAllocations', 'displayMaterialCost', 'materialCostLabel', 'materialVariance'));
    }

    public function confirm(ProductionOrder $productionOrder, ConfirmProductionOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder), 'تم تأكيد أمر الإنتاج.');
    }

    public function hold(ProductionOrder $productionOrder, HoldProductionOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder), 'تم تعليق أمر الإنتاج.');
    }

    public function resume(ProductionOrder $productionOrder, ResumeProductionOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder), 'تم استئناف أمر الإنتاج.');
    }

    public function cancel(ProductionOrder $productionOrder, CancelProductionOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder), 'تم إلغاء أمر الإنتاج.');
    }

    public function complete(ProductionOrder $productionOrder, CompleteProductionOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder), 'تم إغلاق أمر الإنتاج.');
    }

    public function output(RecordProductionOutputRequest $request, ProductionOrder $productionOrder, RecordProductionOutputAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->handle($productionOrder, $request->validated()), 'تم ترحيل الإنتاج التام إلى المخزون.');
    }

    public function destroy(ProductionOrder $productionOrder, DeleteProductionOrderAction $action): RedirectResponse
    {
        try {
            $action->handle($productionOrder);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('production-orders.index')->with('success', 'تم حذف أمر الإنتاج مع الاحتفاظ بسجلاته التاريخية.');
    }

    public function print(ProductionOrder $productionOrder, DocumentPrinter $printer): View
    {
        $productionOrder->load(['materials', 'operations', 'workAssignments.worker', 'workAssignments.operation']);

        return $printer->render($productionOrder, 'production_order');
    }

    private function run(callable $callback, string $message): RedirectResponse
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', $message);
    }
}
