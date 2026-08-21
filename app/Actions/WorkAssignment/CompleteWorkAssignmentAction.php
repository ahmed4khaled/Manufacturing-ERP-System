<?php

namespace App\Actions\WorkAssignment;

use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Enums\WorkAssignmentStatus;
use App\Models\WorkAssignment;
use App\Services\ProductionActivityService;
use App\Services\ProductionSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteWorkAssignmentAction
{
    public function __construct(private readonly ProductionSummaryService $summary, private readonly ProductionActivityService $activity) {}

    public function handle(WorkAssignment $assignment, array $data): WorkAssignment
    {
        return DB::transaction(function () use ($assignment, $data) {
            $assignment = WorkAssignment::query()->with(['productionOrder', 'operation', 'worker', 'materials'])->lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->status === WorkAssignmentStatus::COMPLETED) {
                return $assignment;
            }
            if ($assignment->status !== WorkAssignmentStatus::IN_PROGRESS || $assignment->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'يجب أن يكون التكليف قيد التنفيذ.']);
            }
            $produced = (string) $data['produced_quantity'];
            $good = (string) $data['good_quantity'];
            $defective = (string) $data['defective_quantity'];
            if (bccomp(bcadd($good, $defective, 3), $produced, 3) !== 0) {
                throw ValidationException::withMessages(['produced_quantity' => 'المنتج يجب أن يساوي الجيد + المعيب.']);
            }
            if (bccomp($produced, (string) $assignment->assigned_quantity, 3) > 0) {
                throw ValidationException::withMessages(['produced_quantity' => 'المنتج لا يمكن أن يتجاوز الكمية المسندة.']);
            }
            $reportedDefects = (string) $assignment->defects()->where('status', '!=', ProductionDefectStatus::REJECTED)->sum('quantity');
            if (bccomp($reportedDefects, $defective, 3) !== 0) {
                throw ValidationException::withMessages(['defective_quantity' => 'كمية العيوب المسجلة يجب أن تساوي الكمية المعيبة.']);
            }
            if ($assignment->defects()->where('status', ProductionDefectStatus::PENDING_REVIEW)->exists()) {
                throw ValidationException::withMessages(['defects' => 'يجب مراجعة جميع العيوب قبل إكمال التكليف.']);
            }
            if ($assignment->materialTransactions()->where('type', ProductionMaterialTransactionType::WASTE)->where('status', ProductionMaterialTransactionStatus::PENDING_REVIEW)->exists()) {
                throw ValidationException::withMessages(['materials' => 'يجب مراجعة كل سجلات الهالك قبل إكمال التكليف.']);
            }
            foreach ($assignment->materials as $allocation) {
                $totals = $this->summary->materialTotals($allocation);
                if (bccomp(bcsub($totals['issued'], $totals['returned'], 3), bcadd($totals['used'], $totals['waste'], 3), 3) !== 0) {
                    throw ValidationException::withMessages(['materials' => "خامة {$allocation->productionOrderMaterial->material_name} غير مسواة: المصروف - المرتجع يجب أن يساوي المستخدم + الهالك."]);
                }
            }
            $assignment->update(['produced_quantity' => $produced, 'good_quantity' => $good, 'defective_quantity' => $defective, 'status' => WorkAssignmentStatus::COMPLETED, 'completed_at' => now(), 'completed_by' => auth()->id(), 'notes' => $data['notes'] ?? $assignment->notes]);
            $this->activity->record($assignment->productionOrder, 'assignment_completed', "أكمل العامل {$assignment->worker->name} عملية {$assignment->operation->operation_name}.", ['assignment_id' => $assignment->id, 'good' => $good, 'defective' => $defective]);

            return $assignment->fresh();
        });
    }
}
