<?php

namespace App\Actions\ProductionOrder;

use App\Enums\BomStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\WorkerStatus;
use App\Models\Bom;
use App\Models\ProductionOrder;
use App\Models\Worker;
use App\Services\BomRequirementService;
use App\Services\CodeGeneratorService;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionOrderAction
{
    public function __construct(private readonly CodeGeneratorService $codes, private readonly BomRequirementService $requirements, private readonly ProductionActivityService $activity) {}

    public function handle(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            $bom = Bom::query()->with(['productVariant.product', 'productVariant.color', 'productVariant.size', 'items.material', 'operations'])->lockForUpdate()->findOrFail($data['bom_id']);
            if ($bom->status !== BomStatus::ACTIVE) {
                throw ValidationException::withMessages(['bom_id' => 'يجب اختيار إصدار BOM نشط.']);
            }
            if ((int) $bom->product_id !== (int) $data['product_id'] || (int) $bom->product_variant_id !== (int) $data['product_variant_id']) {
                throw ValidationException::withMessages(['bom_id' => 'BOM لا يتبع المنتج والمتغير المحددين.']);
            }

            $calculated = $this->requirements->calculate($bom, (string) $data['planned_quantity']);
            $requirementInput = $data['requirements'] ?? [];
            $assignments = collect($data['assignments']);
            $validOperationIds = $bom->operations->pluck('id');
            if ($assignments->contains(fn ($a) => ! $validOperationIds->contains((int) $a['bom_operation_id']))) {
                throw ValidationException::withMessages(['assignments' => 'يوجد تكليف لعملية لا تتبع BOM المحدد.']);
            }

            foreach ($bom->operations as $operation) {
                $total = $assignments->where('bom_operation_id', $operation->id)->reduce(fn ($sum, $a) => bcadd($sum, (string) $a['assigned_quantity'], 3), '0.000');
                if (bccomp($total, (string) $data['planned_quantity'], 3) !== 0) {
                    throw ValidationException::withMessages(['assignments' => "إجمالي تكليفات عملية {$operation->operation_name} يجب أن يساوي الكمية المخططة."]);
                }
            }
            $duplicate = $assignments->groupBy(fn ($a) => $a['bom_operation_id'].'-'.$a['worker_id'])->first(fn ($group) => $group->count() > 1);
            if ($duplicate) {
                throw ValidationException::withMessages(['assignments' => 'لا يمكن تكرار العامل في العملية نفسها.']);
            }
            $workers = Worker::query()->whereIn('id', $assignments->pluck('worker_id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($workers as $worker) {
                if ($worker->status !== WorkerStatus::ACTIVE) {
                    throw ValidationException::withMessages(['assignments' => "العامل {$worker->name} غير نشط."]);
                }
            }

            $variant = $bom->productVariant;
            $order = ProductionOrder::create(['order_number' => $this->codes->next('MO'), 'product_id' => $bom->product_id, 'product_variant_id' => $bom->product_variant_id, 'bom_id' => $bom->id, 'product_name' => $variant->product->name, 'color_name' => $variant->color?->name, 'size_name' => $variant->size?->name, 'sku' => $variant->sku, 'bom_number' => $bom->bom_number, 'bom_version' => $bom->version, 'planned_quantity' => $data['planned_quantity'], 'planned_start_date' => $data['planned_start_date'], 'planned_completion_date' => $data['planned_completion_date'] ?? null, 'status' => ProductionOrderStatus::DRAFT, 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id()]);

            $materialMap = [];
            foreach ($calculated as $row) {
                $item = $row['bom_item'];
                $standard = $row['standard_quantity'];
                $override = $requirementInput[$item->id]['required_quantity'] ?? $standard;
                $isOverride = bccomp((string) $override, $standard, 3) !== 0;
                if ($isOverride && blank($requirementInput[$item->id]['override_reason'] ?? null)) {
                    throw ValidationException::withMessages(["requirements.{$item->id}.override_reason" => 'يجب تسجيل سبب تعديل الكمية القياسية.']);
                }
                $snapshot = $order->materials()->create(['bom_item_id' => $item->id, 'material_id' => $item->material_id, 'material_code' => $item->material_code, 'material_name' => $item->material_name, 'unit' => $item->unit, 'standard_quantity' => $standard, 'required_quantity' => $override, 'is_overridden' => $isOverride, 'override_reason' => $isOverride ? $requirementInput[$item->id]['override_reason'] : null, 'overridden_by' => $isOverride ? auth()->id() : null]);
                $materialMap[$item->id] = $snapshot;
            }
            $operationMap = [];
            foreach ($bom->operations as $operation) {
                $operationMap[$operation->id] = $order->operations()->create(['bom_operation_id' => $operation->id, 'manufacturing_operation_id' => $operation->manufacturing_operation_id, 'operation_name' => $operation->operation_name, 'sequence' => $operation->sequence, 'notes' => $operation->notes]);
            }

            $allocatedTotals = collect($materialMap)->mapWithKeys(fn ($m, $id) => [$id => '0.000'])->all();
            foreach ($assignments as $assignmentData) {
                $assignment = $order->workAssignments()->create(['production_order_operation_id' => $operationMap[$assignmentData['bom_operation_id']]->id, 'worker_id' => $assignmentData['worker_id'], 'assigned_quantity' => $assignmentData['assigned_quantity'], 'notes' => $assignmentData['notes'] ?? null, 'assigned_by' => auth()->id()]);
                foreach (($assignmentData['materials'] ?? []) as $bomItemId => $allocationData) {
                    $quantity = (string) ($allocationData['allocated_quantity'] ?? '0');
                    if (bccomp($quantity, '0', 3) <= 0) {
                        continue;
                    }
                    if (! isset($materialMap[$bomItemId])) {
                        throw ValidationException::withMessages(['assignments' => 'توجد خامة مخصصة لا تتبع متطلبات أمر الإنتاج.']);
                    }
                    $standard = bcmul((string) $materialMap[$bomItemId]->required_quantity, bcdiv((string) $assignmentData['assigned_quantity'], (string) $order->planned_quantity, 6), 3);
                    $isOverride = bccomp($quantity, $standard, 3) !== 0;
                    $assignment->materials()->create(['production_order_material_id' => $materialMap[$bomItemId]->id, 'standard_quantity' => $standard, 'allocated_quantity' => $quantity, 'is_overridden' => $isOverride, 'override_reason' => $allocationData['override_reason'] ?? ($isOverride ? 'توزيع يدوي أثناء إنشاء أمر الإنتاج' : null), 'allocated_by' => auth()->id()]);
                    $allocatedTotals[$bomItemId] = bcadd($allocatedTotals[$bomItemId], $quantity, 3);
                }
            }
            foreach ($materialMap as $bomItemId => $material) {
                if (bccomp($allocatedTotals[$bomItemId], (string) $material->required_quantity, 3) !== 0) {
                    throw ValidationException::withMessages(['assignments' => "إجمالي تخصيص خامة {$material->material_name} يجب أن يساوي الكمية المطلوبة {$material->required_quantity}."]);
                }
            }
            $this->activity->record($order, 'created', 'تم إنشاء أمر الإنتاج وتسجيل لقطة BOM والتكليفات والتخصيصات.');

            return $order->fresh(['materials', 'operations', 'workAssignments.materials']);
        });
    }
}
