<?php

namespace App\Actions\ProductionOrder;

use App\Actions\WorkerWage\GenerateProductionWorkerWagesAction;
use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReworkStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Services\Accounting\DocumentPostingService;
use App\Services\InventoryCostingService;
use App\Services\ProductionActivityService;
use App\Services\ProductionSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteProductionOrderAction
{
    public function __construct(
        private readonly ProductionSummaryService $summary,
        private readonly ProductionActivityService $activity,
        private readonly InventoryCostingService $costing,
        private readonly GenerateProductionWorkerWagesAction $workerWages,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->with(['workAssignments.materials', 'operations', 'outputs'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status === ProductionOrderStatus::COMPLETED) {
                return $order;
            }
            if (! in_array($order->status, [ProductionOrderStatus::IN_PROGRESS, ProductionOrderStatus::PARTIALLY_COMPLETED], true)) {
                throw ValidationException::withMessages(['status' => 'حالة أمر الإنتاج لا تسمح بالإغلاق.']);
            }
            if ($order->workAssignments()->where('status', '!=', WorkAssignmentStatus::COMPLETED)->exists()) {
                throw ValidationException::withMessages(['assignments' => 'يجب إكمال جميع تكليفات العمل.']);
            }
            if ($order->defects()->where('status', ProductionDefectStatus::PENDING_REVIEW)->exists()) {
                throw ValidationException::withMessages(['defects' => 'توجد عيوب بانتظار المراجعة.']);
            }
            if ($order->reworks()->whereNotIn('status', [ProductionReworkStatus::COMPLETED, ProductionReworkStatus::CANCELLED])->exists()) {
                throw ValidationException::withMessages(['reworks' => 'توجد إعادة تشغيل غير مكتملة.']);
            }
            if ($order->materialTransactions()->where('status', ProductionMaterialTransactionStatus::PENDING_REVIEW)->exists()) {
                throw ValidationException::withMessages(['materials' => 'توجد حركات خامات بانتظار المراجعة.']);
            }

            $summary = $this->summary->orderSummary($order);
            if (bccomp($summary['output'], $summary['good'], 3) !== 0) {
                throw ValidationException::withMessages(['outputs' => 'يجب ترحيل كل الإنتاج الجيد إلى المخزون قبل الإغلاق.']);
            }
            if (bccomp($summary['output'], '0', 3) <= 0) {
                throw ValidationException::withMessages(['outputs' => 'لا يمكن حساب التكلفة النهائية بدون إنتاج تام.']);
            }

            $costing = $this->costing->finalizeProductionCost($order, $summary['output']);
            if ($costing['total'] === null || $costing['unit'] === null) {
                throw ValidationException::withMessages([
                    'costing' => 'لا يمكن إغلاق أمر الإنتاج لأن خامة مصروفة واحدة على الأقل غير مُقيّمة. يجب تقييم طبقات FIFO أولاً.',
                ]);
            }
            $finalTotalCost = $costing['total'];
            $finalUnitCost = $costing['unit'];
            $order->update([
                'status' => ProductionOrderStatus::COMPLETED,
                'actual_material_cost' => $costing['actual_material_cost'],
                'actual_operation_cost' => $costing['actual_operation_cost'],
                'cost_variance' => $costing['variance'],
                'final_unit_cost' => $finalUnitCost,
                'final_total_cost' => $finalTotalCost,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
            ]);

            foreach ($order->outputs as $output) {
                $output->update([
                    'unit_cost' => $finalUnitCost,
                    'total_cost' => bcmul($finalUnitCost, (string) $output->quantity, 2),
                ]);
            }
            $this->costing->finalizeProductLayers($order, $finalUnitCost);
            ProductVariant::query()->lockForUpdate()->whereKey($order->product_variant_id)->update([
                'actual_cost' => $finalUnitCost,
                'last_production_cost' => $finalUnitCost,
            ]);
            $this->activity->record($order, 'completed', 'تم إغلاق أمر الإنتاج وحساب تكلفة الوحدة النهائية.', [
                'final_unit_cost' => $finalUnitCost,
                'final_total_cost' => $finalTotalCost,
            ]);

            $this->workerWages->handle($order->fresh());
            $this->accounting->production($order->fresh());

            return $order->fresh(['operations', 'outputs']);
        });
    }
}
