<?php

namespace App\Actions\ProductionDefect;

use App\Enums\ProductionDefectStatus;
use App\Models\ProductionDefect;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewDefectAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionDefect $defect, bool $approve): ProductionDefect
    {
        return DB::transaction(function () use ($defect, $approve) {
            $defect = ProductionDefect::query()->with('productionOrder')->lockForUpdate()->findOrFail($defect->id);
            if ($defect->status !== ProductionDefectStatus::PENDING_REVIEW || $defect->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'العيب ليس في حالة تسمح بالمراجعة.']);
            }
            $defect->update(['status' => $approve ? ProductionDefectStatus::APPROVED : ProductionDefectStatus::REJECTED, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $this->activity->record($defect->productionOrder, $approve ? 'defect_approved' : 'defect_rejected', $approve ? 'تم اعتماد العيب.' : 'تم رفض العيب.', ['defect_id' => $defect->id]);

            return $defect;
        });
    }
}
