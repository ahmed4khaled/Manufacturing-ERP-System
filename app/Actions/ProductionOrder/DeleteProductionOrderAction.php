<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteProductionOrderAction
{
    public function handle(ProductionOrder $productionOrder): void
    {
        DB::transaction(function () use ($productionOrder): void {
            $productionOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($productionOrder->id);

            if (! in_array($productionOrder->status, [ProductionOrderStatus::DRAFT, ProductionOrderStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف أمر الإنتاج في حالته الحالية. ألغِ الأمر أولاً، ولا يمكن حذف الأوامر المكتملة.',
                ]);
            }

            $productionOrder->delete();
        });
    }
}
