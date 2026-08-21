<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResumeProductionOrderAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== ProductionOrderStatus::ON_HOLD) {
                throw ValidationException::withMessages(['status' => 'فقط أمر الإنتاج المعلق يمكن استئنافه.']);
            }
            $next = $order->outputs()->exists() ? ProductionOrderStatus::PARTIALLY_COMPLETED : ($order->workAssignments()->whereNotNull('started_at')->exists() ? ProductionOrderStatus::IN_PROGRESS : ProductionOrderStatus::CONFIRMED);
            $order->update(['status' => $next, 'held_by' => null, 'held_at' => null]);
            $this->activity->record($order, 'resumed', 'تم استئناف أمر الإنتاج.');

            return $order;
        });
    }
}
