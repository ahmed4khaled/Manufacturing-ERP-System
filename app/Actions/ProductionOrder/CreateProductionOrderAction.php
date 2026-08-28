<?php

namespace App\Actions\ProductionOrder;

use App\Enums\BomStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\ProductionOrder;
use App\Models\Worker;
use App\Services\BomCostService;
use App\Services\BomRequirementService;
use App\Services\CodeGeneratorService;
use App\Services\ProductionActivityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionOrderAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly BomRequirementService $requirements,
        private readonly BomCostService $costs,
        private readonly ProductionActivityService $activity,
    ) {}

    public function handle(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            $bom = Bom::query()
                ->with([
                    'productVariant.product',
                    'productVariant.color',
                    'productVariant.size',
                    'items.material',
                    'items.operationAllocations.bomOperation',
                    'operations',
                ])
                ->lockForUpdate()
                ->findOrFail($data['bom_id']);

            $this->validateBom($bom, $data);

            $assignments = collect($data['assignments']);
            $this->validateAssignments($bom, $assignments, (string) $data['planned_quantity']);

            $variant = $bom->productVariant;
            $materialCost = $this->costs->resolveStandardMaterialUnitCost($bom, (string) $variant->product->base_cost_price);
            $baseUnitCost = $materialCost['unit_cost'];
            $operationsUnitCost = $bom->operations->reduce(
                fn ($total, $operation) => bcadd($total, (string) $operation->unit_cost, 2),
                '0.00',
            );
            $estimatedUnitCost = bcadd($baseUnitCost, $operationsUnitCost, 2);
            $estimatedTotalCost = bcmul($estimatedUnitCost, (string) $data['planned_quantity'], 2);
            $order = ProductionOrder::create([
                'order_number' => $this->codes->next('MO'),
                'product_id' => $bom->product_id,
                'product_variant_id' => $bom->product_variant_id,
                'bom_id' => $bom->id,
                'product_name' => $variant->product->name,
                'color_name' => $variant->color?->name,
                'size_name' => $variant->size?->name,
                'sku' => $variant->sku,
                'bom_number' => $bom->bom_number,
                'bom_version' => $bom->version,
                'base_unit_cost' => $baseUnitCost,
                'operations_unit_cost' => $operationsUnitCost,
                'estimated_unit_cost' => $estimatedUnitCost,
                'estimated_total_cost' => $estimatedTotalCost,
                'standard_material_cost' => bcmul($baseUnitCost, (string) $data['planned_quantity'], 2),
                'standard_material_cost_source' => $materialCost['source'],
                'standard_operation_cost' => bcmul($operationsUnitCost, (string) $data['planned_quantity'], 2),
                'planned_quantity' => $data['planned_quantity'],
                'planned_start_date' => $data['planned_start_date'],
                'planned_completion_date' => $data['planned_completion_date'] ?? null,
                'status' => ProductionOrderStatus::DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $operationMap = [];
            foreach ($bom->operations as $operation) {
                $operationMap[$operation->id] = $order->operations()->create([
                    'bom_operation_id' => $operation->id,
                    'manufacturing_operation_id' => $operation->manufacturing_operation_id,
                    'operation_name' => $operation->operation_name,
                    'unit_cost' => $operation->unit_cost,
                    'total_cost' => bcmul((string) $operation->unit_cost, (string) $data['planned_quantity'], 2),
                    'sequence' => $operation->sequence,
                    'notes' => $operation->notes,
                ]);
            }

            [$materialMap, $operationRequirementMap] = $this->createMaterialSnapshots(
                $order,
                $bom,
                $operationMap,
                $data['requirements'] ?? [],
            );

            $allocatedTotals = collect($operationRequirementMap)
                ->mapWithKeys(fn ($requirement) => [$requirement->id => '0.000'])
                ->all();

            foreach ($assignments as $assignmentIndex => $assignmentData) {
                $productionOperation = $operationMap[(int) $assignmentData['bom_operation_id']];
                $assignment = $order->workAssignments()->create([
                    'production_order_operation_id' => $productionOperation->id,
                    'worker_id' => $assignmentData['worker_id'],
                    'assigned_quantity' => $assignmentData['assigned_quantity'],
                    'notes' => $assignmentData['notes'] ?? null,
                    'assigned_by' => auth()->id(),
                ]);

                foreach (($assignmentData['materials'] ?? []) as $bomItemId => $allocationData) {
                    $mapKey = $this->requirementMapKey((int) $bomItemId, (int) $assignmentData['bom_operation_id']);
                    $operationRequirement = $operationRequirementMap[$mapKey] ?? null;
                    if (! $operationRequirement || ! isset($materialMap[$bomItemId])) {
                        throw ValidationException::withMessages([
                            "assignments.{$assignmentIndex}.materials.{$bomItemId}" => 'الخامة المرسلة غير مرتبطة بهذه العملية في BOM.',
                        ]);
                    }

                    $quantity = (string) ($allocationData['allocated_quantity'] ?? '0');
                    if (bccomp($quantity, '0', 3) < 0) {
                        throw ValidationException::withMessages([
                            "assignments.{$assignmentIndex}.materials.{$bomItemId}.allocated_quantity" => 'الكمية المخصصة لا يمكن أن تكون سالبة.',
                        ]);
                    }
                    if (bccomp($quantity, '0', 3) <= 0) {
                        continue;
                    }

                    $suggested = bcmul(
                        (string) $operationRequirement->required_quantity,
                        bcdiv((string) $assignmentData['assigned_quantity'], (string) $order->planned_quantity, 6),
                        3,
                    );
                    $isOverride = bccomp($quantity, $suggested, 3) !== 0;
                    $assignment->materials()->create([
                        'production_order_material_id' => $materialMap[$bomItemId]->id,
                        'production_order_material_operation_id' => $operationRequirement->id,
                        'standard_quantity' => $suggested,
                        'allocated_quantity' => $quantity,
                        'is_overridden' => $isOverride,
                        'override_reason' => $allocationData['override_reason'] ?? ($isOverride ? 'توزيع يدوي أثناء إنشاء أمر الإنتاج' : null),
                        'allocated_by' => auth()->id(),
                    ]);
                    $allocatedTotals[$operationRequirement->id] = bcadd($allocatedTotals[$operationRequirement->id], $quantity, 3);
                }
            }

            foreach ($operationRequirementMap as $operationRequirement) {
                if (bccomp($allocatedTotals[$operationRequirement->id], (string) $operationRequirement->required_quantity, 3) !== 0) {
                    $operationRequirement->loadMissing(['productionOrderMaterial', 'productionOrderOperation']);
                    throw ValidationException::withMessages([
                        'assignments' => "تخصيص خامة {$operationRequirement->productionOrderMaterial->material_name} لعملية {$operationRequirement->productionOrderOperation->operation_name} يجب أن يساوي {$operationRequirement->required_quantity}.",
                    ]);
                }
            }

            $this->activity->record($order, 'created', 'تم إنشاء أمر الإنتاج وحفظ لقطة BOM وتوزيع الخامات على العمليات والتكليفات.');

            return $order->fresh([
                'materials.operationRequirements',
                'operations.materialRequirements',
                'workAssignments.materials.operationRequirement',
            ]);
        });
    }

    private function validateBom(Bom $bom, array $data): void
    {
        if ($bom->status !== BomStatus::ACTIVE) {
            throw ValidationException::withMessages(['bom_id' => 'يجب اختيار إصدار BOM نشط.']);
        }
        if ((int) $bom->product_id !== (int) $data['product_id'] || (int) $bom->product_variant_id !== (int) $data['product_variant_id']) {
            throw ValidationException::withMessages(['bom_id' => 'BOM لا يتبع المنتج والمتغير المحددين.']);
        }
    }

    private function validateAssignments(Bom $bom, Collection $assignments, string $plannedQuantity): void
    {
        if ($assignments->contains(fn ($assignment) => bccomp((string) $assignment['assigned_quantity'], '0', 3) <= 0)) {
            throw ValidationException::withMessages(['assignments' => 'كمية كل تكليف يجب أن تكون أكبر من صفر.']);
        }

        $validOperationIds = $bom->operations->pluck('id');
        if ($assignments->contains(fn ($assignment) => ! $validOperationIds->contains((int) $assignment['bom_operation_id']))) {
            throw ValidationException::withMessages(['assignments' => 'يوجد تكليف لعملية لا تتبع BOM المحدد.']);
        }

        foreach ($bom->operations as $operation) {
            $total = $assignments->where('bom_operation_id', $operation->id)
                ->reduce(fn ($sum, $assignment) => bcadd($sum, (string) $assignment['assigned_quantity'], 3), '0.000');
            if (bccomp($total, $plannedQuantity, 3) !== 0) {
                throw ValidationException::withMessages(['assignments' => "إجمالي تكليفات عملية {$operation->operation_name} يجب أن يساوي الكمية المخططة."]);
            }
        }

        if ($assignments->groupBy(fn ($assignment) => $assignment['bom_operation_id'].'-'.$assignment['worker_id'])->contains(fn ($group) => $group->count() > 1)) {
            throw ValidationException::withMessages(['assignments' => 'لا يمكن تكرار العامل في العملية نفسها.']);
        }

        $workerIds = $assignments->pluck('worker_id')->unique();
        $workers = Worker::query()->whereIn('id', $workerIds)->lockForUpdate()->get()->keyBy('id');
        if ($workers->count() !== $workerIds->count()) {
            throw ValidationException::withMessages(['assignments' => 'أحد العمال المحددين غير موجود.']);
        }
        foreach ($workers as $worker) {
            if ($worker->status !== WorkerStatus::ACTIVE) {
                throw ValidationException::withMessages(['assignments' => "العامل {$worker->name} غير نشط."]);
            }
        }
    }

    private function createMaterialSnapshots(ProductionOrder $order, Bom $bom, array $operationMap, array $requirementInput): array
    {
        $calculated = $this->requirements->calculate($bom, (string) $order->planned_quantity);
        $materialMap = [];
        $operationRequirementMap = [];

        foreach ($calculated as $row) {
            /** @var BomItem $item */
            $item = $row['bom_item'];
            $standard = $row['standard_quantity'];
            $required = (string) ($requirementInput[$item->id]['required_quantity'] ?? $standard);
            if (bccomp($required, '0', 3) <= 0) {
                throw ValidationException::withMessages(["requirements.{$item->id}.required_quantity" => 'متطلب الخامة يجب أن يكون أكبر من صفر.']);
            }
            $isOverride = bccomp($required, $standard, 3) !== 0;
            if ($isOverride && blank($requirementInput[$item->id]['override_reason'] ?? null)) {
                throw ValidationException::withMessages(["requirements.{$item->id}.override_reason" => 'يجب تسجيل سبب تعديل الكمية القياسية.']);
            }

            $material = $order->materials()->create([
                'bom_item_id' => $item->id,
                'material_id' => $item->material_id,
                'material_code' => $item->material_code,
                'material_name' => $item->material_name,
                'unit' => $item->unit,
                'standard_quantity' => $standard,
                'required_quantity' => $required,
                'standard_unit_cost' => $item->standard_unit_cost,
                'standard_total_cost' => $item->standard_unit_cost === null
                    ? null
                    : bcmul($standard, (string) $item->standard_unit_cost, 2),
                'is_overridden' => $isOverride,
                'override_reason' => $isOverride ? $requirementInput[$item->id]['override_reason'] : null,
                'overridden_by' => $isOverride ? auth()->id() : null,
            ]);
            $materialMap[$item->id] = $material;

            $bomAllocations = $item->operationAllocations->sortBy('bomOperation.sequence')->values();
            if ($bomAllocations->isEmpty()) {
                $firstOperation = $bom->operations->sortBy('sequence')->first();
                $bomAllocations = collect([(object) ['bom_operation_id' => $firstOperation->id, 'quantity' => $item->quantity]]);
            }

            $standardSplits = $this->splitQuantity($item, $bomAllocations, $standard);
            $requiredSplits = $this->splitQuantity($item, $bomAllocations, $required);
            foreach ($bomAllocations as $allocation) {
                $bomOperationId = (int) $allocation->bom_operation_id;
                if (! isset($operationMap[$bomOperationId])) {
                    throw ValidationException::withMessages(['bom_id' => 'توزيع خامات BOM يشير إلى عملية غير موجودة.']);
                }
                $snapshot = $material->operationRequirements()->create([
                    'production_order_operation_id' => $operationMap[$bomOperationId]->id,
                    'standard_quantity' => $standardSplits[$bomOperationId],
                    'required_quantity' => $requiredSplits[$bomOperationId],
                ]);
                $operationRequirementMap[$this->requirementMapKey($item->id, $bomOperationId)] = $snapshot;
            }
        }

        return [$materialMap, $operationRequirementMap];
    }

    private function splitQuantity(BomItem $item, Collection $allocations, string $total): array
    {
        $result = [];
        $allocated = '0.000';
        $lastIndex = $allocations->count() - 1;

        foreach ($allocations as $index => $allocation) {
            $quantity = $index === $lastIndex
                ? bcsub($total, $allocated, 3)
                : bcmul(bcdiv((string) $allocation->quantity, (string) $item->quantity, 6), $total, 3);
            $result[(int) $allocation->bom_operation_id] = $quantity;
            $allocated = bcadd($allocated, $quantity, 3);
        }

        return $result;
    }

    private function requirementMapKey(int $bomItemId, int $bomOperationId): string
    {
        return $bomItemId.'-'.$bomOperationId;
    }
}
