<?php

namespace App\Actions\WorkAssignment;

use App\Enums\ProductionOrderStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionOrder;
use App\Models\WorkAssignment;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartWorkAssignmentAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(WorkAssignment $assignment): WorkAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment = WorkAssignment::query()->with(['operation', 'worker'])->lockForUpdate()->findOrFail($assignment->id);
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($assignment->production_order_id);
            if ($assignment->status === WorkAssignmentStatus::IN_PROGRESS) {
                return $assignment;
            }
            if ($assignment->status !== WorkAssignmentStatus::ASSIGNED || ! in_array($order->status, [ProductionOrderStatus::CONFIRMED, ProductionOrderStatus::IN_PROGRESS, ProductionOrderStatus::PARTIALLY_COMPLETED], true)) {
                throw ValidationException::withMessages(['status' => 'لا يمكن بدء هذا التكليف في حالته الحالية.']);
            }
            $previousIds = $order->operations()->where('sequence', '<', $assignment->operation->sequence)->pluck('id');
            if ($order->workAssignments()->whereIn('production_order_operation_id', $previousIds)->where('status', '!=', WorkAssignmentStatus::COMPLETED)->exists()) {
                throw ValidationException::withMessages(['status' => 'يجب إكمال العمليات السابقة أولاً.']);
            }
            if ($order->reworks()->whereIn('production_order_operation_id', $previousIds)->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['status' => 'يجب إكمال إعادة التشغيل للعمليات السابقة أولاً.']);
            }
            $assignment->update(['status' => WorkAssignmentStatus::IN_PROGRESS, 'started_at' => now()]);
            if ($order->status === ProductionOrderStatus::CONFIRMED) {
                $order->update(['status' => ProductionOrderStatus::IN_PROGRESS]);
            }
            $this->activity->record($order, 'assignment_started', "بدأ العامل {$assignment->worker->name} عملية {$assignment->operation->operation_name}.", ['assignment_id' => $assignment->id]);

            return $assignment;
        });
    }
}
