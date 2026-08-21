<?php

namespace App\Actions\ProductionRework;

use App\Enums\ProductionReworkStatus;
use App\Models\ProductionRework;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteReworkAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionRework $rework, array $data): ProductionRework
    {
        return DB::transaction(function () use ($rework, $data) {
            $rework = ProductionRework::query()->with(['productionOrder', 'worker'])->lockForUpdate()->findOrFail($rework->id);
            if ($rework->status === ProductionReworkStatus::COMPLETED) {
                return $rework;
            }
            if ($rework->status !== ProductionReworkStatus::IN_PROGRESS || $rework->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'إعادة التشغيل ليست قيد التنفيذ.']);
            }
            $total = bcadd((string) $data['good_quantity'], (string) $data['defective_quantity'], 3);
            if (bccomp($total, (string) $rework->assigned_quantity, 3) !== 0) {
                throw ValidationException::withMessages(['good_quantity' => 'الجيد + المعيب يجب أن يساوي كمية إعادة التشغيل.']);
            }
            $rework->update(['good_quantity' => $data['good_quantity'], 'defective_quantity' => $data['defective_quantity'], 'status' => ProductionReworkStatus::COMPLETED, 'completed_by' => auth()->id(), 'completed_at' => now(), 'notes' => $data['notes'] ?? $rework->notes]);
            $this->activity->record($rework->productionOrder, 'rework_completed', "أكمل العامل {$rework->worker->name} إعادة التشغيل.", ['rework_id' => $rework->id, 'good' => $data['good_quantity'], 'defective' => $data['defective_quantity']]);

            return $rework;
        });
    }
}
