<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HoldProductionOrderAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, [ProductionOrderStatus::CONFIRMED, ProductionOrderStatus::IN_PROGRESS, ProductionOrderStatus::PARTIALLY_COMPLETED], true)) {
                throw ValidationException::withMessages(['status' => 'حالة أمر الإنتاج لا تسمح بالتعليق.']);
            }
            $order->update(['status' => ProductionOrderStatus::ON_HOLD, 'held_by' => auth()->id(), 'held_at' => now()]);
            $this->activity->record($order, 'held', 'تم تعليق أمر الإنتاج.');

            return $order;
        });
    }
}
