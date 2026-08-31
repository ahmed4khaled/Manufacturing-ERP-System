<?php

namespace App\Actions\WorkerWage;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionWorkerWageStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateProductionWorkerWagesAction
{
    /** @return Collection<int, ProductionWorkerWage> */
    public function handle(ProductionOrder $order): Collection
    {
        return DB::transaction(function () use ($order): Collection {
            $order = ProductionOrder::query()
                ->with(['workAssignments.operation'])
                ->lockForUpdate()
                ->findOrFail($order->id);
            if ($order->status !== ProductionOrderStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'wages' => 'لا يمكن إثبات أجور الإنتاج قبل إكمال أمر الإنتاج.',
                ]);
            }

            foreach ($order->workAssignments as $assignment) {
                if ($assignment->status !== WorkAssignmentStatus::COMPLETED) {
                    throw ValidationException::withMessages([
                        'wages' => 'لا يمكن إثبات أجور الإنتاج قبل إكمال جميع تكليفات العمل.',
                    ]);
                }

                $quantity = (string) $assignment->assigned_quantity;
                $unitRate = (string) $assignment->operation->unit_cost;
                $gross = bcmul($quantity, $unitRate, 2);

                ProductionWorkerWage::query()->firstOrCreate(
                    ['work_assignment_id' => $assignment->id],
                    [
                        'worker_id' => $assignment->worker_id,
                        'production_order_id' => $order->id,
                        'production_order_operation_id' => $assignment->production_order_operation_id,
                        'quantity' => $quantity,
                        'unit_rate' => $unitRate,
                        'gross_amount' => $gross,
                        'paid_amount' => 0,
                        'remaining_amount' => $gross,
                        'status' => bccomp($gross, '0', 2) > 0
                            ? ProductionWorkerWageStatus::UNPAID
                            : ProductionWorkerWageStatus::PAID,
                        'earned_at' => $order->closed_at?->toDateString() ?? now()->toDateString(),
                    ],
                );
            }

            $wages = ProductionWorkerWage::query()
                ->where('production_order_id', $order->id)
                ->with(['worker', 'operation'])
                ->orderBy('id')
                ->get();
            $gross = $wages->reduce(
                fn (string $sum, ProductionWorkerWage $wage) => bcadd($sum, (string) $wage->gross_amount, 2),
                '0.00',
            );

            if (bccomp($gross, (string) $order->actual_operation_cost, 2) !== 0) {
                throw ValidationException::withMessages([
                    'wages' => "إجمالي استحقاقات العمال ({$gross}) لا يطابق تكلفة التشغيل المثبتة ({$order->actual_operation_cost}).",
                ]);
            }

            return $wages;
        });
    }
}
