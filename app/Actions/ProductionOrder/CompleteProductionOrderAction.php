<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReworkStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionOrder;
use App\Services\ProductionActivityService;
use App\Services\ProductionSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteProductionOrderAction
{
    public function __construct(private readonly ProductionSummaryService $summary, private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->with(['workAssignments.materials', 'operations'])->lockForUpdate()->findOrFail($order->id);
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
            $order->update(['status' => ProductionOrderStatus::COMPLETED, 'closed_by' => auth()->id(), 'closed_at' => now()]);
            $this->activity->record($order, 'completed', 'تم إغلاق أمر الإنتاج.');

            return $order;
        });
    }
}
