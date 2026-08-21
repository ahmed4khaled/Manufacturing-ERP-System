<?php

namespace App\Actions\ProductionDefect;

use App\Enums\ProductionDefectStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionDefect;
use App\Models\WorkAssignment;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDefectAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(WorkAssignment $assignment, array $data): ProductionDefect
    {
        return DB::transaction(function () use ($assignment, $data) {
            $assignment = WorkAssignment::query()->with(['productionOrder', 'worker', 'operation'])->lockForUpdate()->findOrFail($assignment->id);
            if (! in_array($assignment->status, [WorkAssignmentStatus::IN_PROGRESS, WorkAssignmentStatus::ASSIGNED], true) || $assignment->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تسجيل عيب لهذا التكليف.']);
            }
            $existing = (string) $assignment->defects()->where('status', '!=', ProductionDefectStatus::REJECTED)->sum('quantity');
            if (bccomp(bcadd($existing, (string) $data['quantity'], 3), (string) $assignment->assigned_quantity, 3) > 0) {
                throw ValidationException::withMessages(['quantity' => 'إجمالي العيوب يتجاوز الكمية المسندة.']);
            }
            $defect = ProductionDefect::create(['production_order_id' => $assignment->production_order_id, 'work_assignment_id' => $assignment->id, 'worker_id' => $assignment->worker_id, 'production_order_operation_id' => $assignment->production_order_operation_id, 'product_variant_id' => $assignment->productionOrder->product_variant_id, 'quantity' => $data['quantity'], 'reason' => $data['reason'], 'status' => ProductionDefectStatus::PENDING_REVIEW, 'notes' => $data['notes'] ?? null, 'recorded_by' => auth()->id()]);
            $this->activity->record($assignment->productionOrder, 'defect_recorded', "تم تسجيل عيب للعامل {$assignment->worker->name} في عملية {$assignment->operation->operation_name}.", ['defect_id' => $defect->id, 'quantity' => $defect->quantity]);

            return $defect;
        });
    }
}
