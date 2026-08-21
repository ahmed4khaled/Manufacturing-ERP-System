<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionMaterialTransactionType;
use App\Enums\ProductionOrderStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionOrder;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelProductionOrderAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === ProductionOrderStatus::CANCELLED) {
                return $order;
            }
            if ($order->status === ProductionOrderStatus::COMPLETED) {
                throw ValidationException::withMessages(['status' => 'لا يمكن إلغاء أمر إنتاج مكتمل.']);
            }
            if ($order->outputs()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['status' => 'لا يمكن إلغاء أمر تم ترحيل إنتاج تام له.']);
            }
            $issued = (string) $order->materialTransactions()->where('type', ProductionMaterialTransactionType::ISSUE)->sum('quantity');
            $returned = (string) $order->materialTransactions()->where('type', ProductionMaterialTransactionType::RETURN)->sum('quantity');
            if (bccomp($issued, $returned, 3) !== 0) {
                throw ValidationException::withMessages(['materials' => 'يجب إعادة جميع الخامات المصروفة قبل إلغاء أمر الإنتاج.']);
            }
            $order->workAssignments()->whereNot('status', WorkAssignmentStatus::COMPLETED)->update(['status' => WorkAssignmentStatus::CANCELLED]);
            $order->update(['status' => ProductionOrderStatus::CANCELLED, 'cancelled_by' => auth()->id(), 'cancelled_at' => now()]);
            $this->activity->record($order, 'cancelled', 'تم إلغاء أمر الإنتاج.');

            return $order;
        });
    }
}
