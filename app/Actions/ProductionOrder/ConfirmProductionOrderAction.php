<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Services\MaterialStockService;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmProductionOrderAction
{
    public function __construct(private readonly MaterialStockService $stock, private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->with(['materials', 'operations.workAssignments.materials'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status === ProductionOrderStatus::CONFIRMED) {
                return $order;
            }
            if ($order->status !== ProductionOrderStatus::DRAFT) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تأكيد إلا أمر إنتاج مسودة.']);
            }
            foreach ($order->operations as $operation) {
                $assigned = (string) $operation->workAssignments->sum('assigned_quantity');
                if (bccomp($assigned, (string) $order->planned_quantity, 3) !== 0) {
                    throw ValidationException::withMessages(['assignments' => "تكليفات عملية {$operation->operation_name} غير مكتملة."]);
                }
            }
            foreach ($order->materials as $material) {
                $allocated = (string) $material->assignmentMaterials()->sum('allocated_quantity');
                if (bccomp($allocated, (string) $material->required_quantity, 3) !== 0) {
                    throw ValidationException::withMessages(['materials' => "تخصيص خامة {$material->material_name} غير مكتمل."]);
                }
            }
            $this->stock->validateAvailability($order->materials->map(fn ($m) => (object) ['material_id' => $m->material_id, 'quantity' => $m->required_quantity]));
            $order->update(['status' => ProductionOrderStatus::CONFIRMED, 'confirmed_by' => auth()->id(), 'confirmed_at' => now()]);
            $this->activity->record($order, 'confirmed', 'تم تأكيد أمر الإنتاج بعد التحقق من الخامات والتكليفات.');

            return $order->fresh();
        });
    }
}
