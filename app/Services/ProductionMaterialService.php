<?php

namespace App\Services;

use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Enums\ProductionOrderStatus;
use App\Enums\WorkAssignmentStatus;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\WorkAssignment;
use App\Models\WorkAssignmentMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionMaterialService
{
    public function __construct(private readonly MaterialStockService $stock, private readonly ProductionSummaryService $summary, private readonly ProductionActivityService $activity) {}

    public function record(WorkAssignmentMaterial $allocation, ProductionMaterialTransactionType $type, array $data): ProductionMaterialTransaction
    {
        return DB::transaction(function () use ($allocation, $type, $data) {
            $allocation = WorkAssignmentMaterial::query()->with(['workAssignment.productionOrder', 'productionOrderMaterial.material'])->lockForUpdate()->findOrFail($allocation->id);
            $assignment = WorkAssignment::query()->lockForUpdate()->findOrFail($allocation->work_assignment_id);
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($assignment->production_order_id);
            if (! in_array($order->status, [ProductionOrderStatus::CONFIRMED, ProductionOrderStatus::IN_PROGRESS, ProductionOrderStatus::PARTIALLY_COMPLETED], true)) {
                throw ValidationException::withMessages(['status' => 'حالة أمر الإنتاج لا تسمح بتسجيل حركة خامة.']);
            }
            if ($assignment->status === WorkAssignmentStatus::CANCELLED || $assignment->status === WorkAssignmentStatus::COMPLETED) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تسجيل خامات لتكليف مكتمل أو ملغي.']);
            }
            $quantity = (string) $data['quantity'];
            $totals = $this->summary->materialTotals($allocation);
            if ($type === ProductionMaterialTransactionType::ISSUE && bccomp(bcadd($totals['issued'], $quantity, 3), (string) $allocation->allocated_quantity, 3) > 0) {
                throw ValidationException::withMessages(['quantity' => 'الكمية المصروفة تتجاوز الكمية المخصصة للتكليف.']);
            }
            if ($type !== ProductionMaterialTransactionType::ISSUE) {
                $accounted = bcadd(bcadd($totals['used'], $totals['returned'], 3), $totals['waste'], 3);
                if (bccomp(bcadd($accounted, $quantity, 3), $totals['issued'], 3) > 0) {
                    throw ValidationException::withMessages(['quantity' => 'الكمية تتجاوز الرصيد غير المسوى من الخامة المصروفة.']);
                }
            }
            $transaction = ProductionMaterialTransaction::create([
                'production_order_id' => $order->id, 'work_assignment_id' => $assignment->id, 'work_assignment_material_id' => $allocation->id,
                'worker_id' => $assignment->worker_id, 'material_id' => $allocation->productionOrderMaterial->material_id,
                'type' => $type, 'quantity' => $quantity,
                'status' => $type === ProductionMaterialTransactionType::WASTE ? ProductionMaterialTransactionStatus::PENDING_REVIEW : ProductionMaterialTransactionStatus::RECORDED,
                'occurred_at' => $data['occurred_at'], 'notes' => $data['notes'] ?? null, 'recorded_by' => auth()->id(),
            ]);
            $transaction->load(['productionOrder', 'worker']);
            if ($type === ProductionMaterialTransactionType::ISSUE) {
                $this->stock->createProductionIssueMovement($transaction);
            }
            if ($type === ProductionMaterialTransactionType::RETURN) {
                $this->stock->createProductionReturnMovement($transaction);
            }
            $this->activity->record($order, 'material_'.$type->value, "تم تسجيل {$type->label()} خامة {$allocation->productionOrderMaterial->material_name} للعامل {$assignment->worker->name}.", ['transaction_id' => $transaction->id, 'quantity' => $quantity]);

            return $transaction;
        });
    }
}
