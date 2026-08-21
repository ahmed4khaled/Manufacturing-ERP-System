<?php

namespace App\Actions\ProductionRework;

use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionReworkStatus;
use App\Enums\WorkerStatus;
use App\Models\ProductionDefect;
use App\Models\ProductionRework;
use App\Models\Worker;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReworkAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionDefect $defect, array $data): ProductionRework
    {
        return DB::transaction(function () use ($defect, $data) {
            $defect = ProductionDefect::query()->with('productionOrder')->lockForUpdate()->findOrFail($defect->id);
            if (! in_array($defect->status, [ProductionDefectStatus::PENDING_REVIEW, ProductionDefectStatus::APPROVED, ProductionDefectStatus::REWORK], true) || $defect->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'العيب لا يسمح بإنشاء إعادة تشغيل.']);
            }
            if (! $defect->productionOrder->operations()->whereKey($data['production_order_operation_id'])->exists()) {
                throw ValidationException::withMessages(['production_order_operation_id' => 'العملية لا تتبع أمر الإنتاج.']);
            }
            $worker = Worker::query()->lockForUpdate()->findOrFail($data['worker_id']);
            if ($worker->status !== WorkerStatus::ACTIVE) {
                throw ValidationException::withMessages(['worker_id' => 'العامل غير نشط.']);
            }
            $assigned = (string) $defect->reworks()->where('status', '!=', ProductionReworkStatus::CANCELLED)->sum('assigned_quantity');
            if (bccomp(bcadd($assigned, (string) $data['assigned_quantity'], 3), (string) $defect->quantity, 3) > 0) {
                throw ValidationException::withMessages(['assigned_quantity' => 'كمية إعادة التشغيل تتجاوز كمية العيب.']);
            }
            $rework = ProductionRework::create(['production_defect_id' => $defect->id, 'production_order_id' => $defect->production_order_id, 'source_work_assignment_id' => $defect->work_assignment_id, 'production_order_operation_id' => $data['production_order_operation_id'], 'worker_id' => $worker->id, 'assigned_quantity' => $data['assigned_quantity'], 'status' => ProductionReworkStatus::ASSIGNED, 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id()]);
            $defect->update(['status' => ProductionDefectStatus::REWORK, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $this->activity->record($defect->productionOrder, 'rework_created', "تم إسناد إعادة تشغيل إلى العامل {$worker->name}.", ['defect_id' => $defect->id, 'rework_id' => $rework->id]);

            return $rework;
        });
    }
}
