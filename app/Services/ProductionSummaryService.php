<?php

namespace App\Services;

use App\Enums\ProductionDefectStatus;
use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Enums\ProductionReworkStatus;
use App\Models\ProductionOrder;
use App\Models\WorkAssignmentMaterial;

class ProductionSummaryService
{
    public function materialTotals(WorkAssignmentMaterial $allocation): array
    {
        $totals = $allocation->transactions()->selectRaw('type, SUM(quantity) total')->where('status', '!=', ProductionMaterialTransactionStatus::REJECTED)->groupBy('type')->pluck('total', 'type');

        return [
            'issued' => bcadd((string) ($totals[ProductionMaterialTransactionType::ISSUE->value] ?? 0), '0', 3),
            'used' => bcadd((string) ($totals[ProductionMaterialTransactionType::USAGE->value] ?? 0), '0', 3),
            'returned' => bcadd((string) ($totals[ProductionMaterialTransactionType::RETURN->value] ?? 0), '0', 3),
            'waste' => bcadd((string) ($totals[ProductionMaterialTransactionType::WASTE->value] ?? 0), '0', 3),
        ];
    }

    public function orderSummary(ProductionOrder $order): array
    {
        $assignments = $order->workAssignments;
        $finalOperationId = $order->operations()->orderByDesc('sequence')->value('id');
        $finalAssignments = $assignments->where('production_order_operation_id', $finalOperationId);
        $reworkGood = (string) $order->reworks()->where('production_order_operation_id', $finalOperationId)->where('status', ProductionReworkStatus::COMPLETED)->sum('good_quantity');
        $good = bcadd((string) $finalAssignments->sum('good_quantity'), $reworkGood, 3);
        $produced = (string) $finalAssignments->sum('produced_quantity');
        $defective = (string) $finalAssignments->sum('defective_quantity');
        $output = (string) $order->outputs()->sum('quantity');

        return [
            'planned' => (string) $order->planned_quantity,
            'assigned' => (string) $order->planned_quantity,
            'work_assignment_load' => bcadd((string) $assignments->sum('assigned_quantity'), '0', 3),
            'produced' => bcadd($produced, $reworkGood, 3), 'good' => $good, 'defective' => $defective,
            'remaining' => bccomp((string) $order->planned_quantity, $good, 3) > 0 ? bcsub((string) $order->planned_quantity, $good, 3) : '0.000',
            'output' => bcadd($output, '0', 3),
            'pending_defects' => $order->defects()->where('status', ProductionDefectStatus::PENDING_REVIEW)->count(),
        ];
    }
}
