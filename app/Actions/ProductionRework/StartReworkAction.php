<?php

namespace App\Actions\ProductionRework;

use App\Enums\ProductionReworkStatus;
use App\Models\ProductionRework;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartReworkAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionRework $rework): ProductionRework
    {
        return DB::transaction(function () use ($rework) {
            $rework = ProductionRework::query()->with(['productionOrder', 'worker'])->lockForUpdate()->findOrFail($rework->id);
            if ($rework->status === ProductionReworkStatus::IN_PROGRESS) {
                return $rework;
            }
            if ($rework->status !== ProductionReworkStatus::ASSIGNED || $rework->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'لا يمكن بدء إعادة التشغيل.']);
            }
            $rework->update(['status' => ProductionReworkStatus::IN_PROGRESS, 'started_at' => now()]);
            $this->activity->record($rework->productionOrder, 'rework_started', "بدأ العامل {$rework->worker->name} إعادة التشغيل.", ['rework_id' => $rework->id]);

            return $rework;
        });
    }
}
