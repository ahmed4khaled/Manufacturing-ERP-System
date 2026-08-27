<?php

namespace App\Actions\Bom;

use App\Enums\BomStatus;
use App\Enums\ManufacturingOperationStatus;
use App\Enums\MaterialStatus;
use App\Models\Bom;
use App\Models\ManufacturingOperation;
use App\Models\Material;
use App\Models\ProductVariant;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBomAction
{
    public function __construct(private readonly CodeGeneratorService $codes) {}

    public function handle(array $data): Bom
    {
        return DB::transaction(function () use ($data) {
            $variant = ProductVariant::query()->with('product')->lockForUpdate()->findOrFail($data['product_variant_id']);
            if ((int) $variant->product_id !== (int) $data['product_id']) {
                throw ValidationException::withMessages(['product_variant_id' => 'المتغير المحدد لا يتبع المنتج المحدد.']);
            }
            if (Bom::query()->where('product_variant_id', $variant->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'يوجد BOM لهذا المتغير بالفعل. أنشئ إصداراً جديداً من شاشة BOM الحالية.']);
            }
            $materials = Material::query()->whereIn('id', collect($data['items'])->pluck('material_id'))->lockForUpdate()->get()->keyBy('id');
            $operations = ManufacturingOperation::query()->whereIn('id', collect($data['operations'])->pluck('manufacturing_operation_id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($materials as $material) {
                if ($material->status !== MaterialStatus::ACTIVE) {
                    throw ValidationException::withMessages(['items' => "الخامة {$material->name} غير نشطة."]);
                }
            }
            foreach ($operations as $operation) {
                if ($operation->status !== ManufacturingOperationStatus::ACTIVE) {
                    throw ValidationException::withMessages(['operations' => "العملية {$operation->name} غير نشطة."]);
                }
            }

            $bom = Bom::create(['bom_number' => $this->codes->next('BOM'), 'version' => 1, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => $data['basis_quantity'], 'notes' => $data['notes'] ?? null, 'status' => BomStatus::DRAFT, 'created_by' => auth()->id()]);
            $this->replaceDetails($bom, $data, $materials, $operations);

            return $bom->fresh(['product', 'productVariant.color', 'productVariant.size', 'items.operationAllocations', 'operations']);
        });
    }

    public function replaceDetails(Bom $bom, array $data, $materials = null, $operations = null): void
    {
        $materials ??= Material::query()->whereIn('id', collect($data['items'])->pluck('material_id'))->get()->keyBy('id');
        $operations ??= ManufacturingOperation::query()->whereIn('id', collect($data['operations'])->pluck('manufacturing_operation_id'))->get()->keyBy('id');
        $bom->items()->delete();
        $bom->operations()->delete();

        $operationMap = [];
        foreach (array_values($data['operations']) as $index => $item) {
            $operation = $operations->get($item['manufacturing_operation_id']);
            $unitCost = (string) ($item['unit_cost'] ?? $operation->default_unit_cost ?? 0);
            if (bccomp($unitCost, '0', 2) < 0) {
                throw ValidationException::withMessages(["operations.{$index}.unit_cost" => 'تكلفة العملية لا يمكن أن تكون سالبة.']);
            }
            $operationMap[$operation->id] = $bom->operations()->create(['manufacturing_operation_id' => $operation->id, 'operation_name' => $operation->name, 'unit_cost' => $unitCost, 'sequence' => $index + 1, 'notes' => $item['notes'] ?? null]);
        }

        foreach ($data['items'] as $itemIndex => $item) {
            $material = $materials->get($item['material_id']);
            $bomItem = $bom->items()->create(['material_id' => $material->id, 'material_code' => $material->code, 'material_name' => $material->name, 'unit' => $material->unit->value, 'quantity' => $item['quantity']]);
            $allocations = array_values($item['operation_allocations'] ?? []);

            // Preserve the legacy behavior for old clients and BOM records.
            if ($allocations === []) {
                $firstOperation = collect($operationMap)->sortBy('sequence')->first();
                $allocations = [['manufacturing_operation_id' => $firstOperation->manufacturing_operation_id, 'quantity' => $item['quantity']]];
            }

            $operationIds = collect($allocations)->pluck('manufacturing_operation_id');
            if ($operationIds->unique()->count() !== $operationIds->count()) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.operation_allocations" => "لا يمكن تكرار العملية لنفس الخامة {$material->name}.",
                ]);
            }

            $total = '0.000';
            foreach ($allocations as $allocationIndex => $allocation) {
                $operation = $operationMap[(int) $allocation['manufacturing_operation_id']] ?? null;
                if (! $operation) {
                    throw ValidationException::withMessages([
                        "items.{$itemIndex}.operation_allocations.{$allocationIndex}.manufacturing_operation_id" => 'عملية استهلاك الخامة يجب أن تكون ضمن تسلسل عمليات BOM.',
                    ]);
                }

                $quantity = (string) $allocation['quantity'];
                if (bccomp($quantity, '0', 3) <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$itemIndex}.operation_allocations.{$allocationIndex}.quantity" => 'كمية الخامة للعملية يجب أن تكون أكبر من صفر.',
                    ]);
                }
                $total = bcadd($total, $quantity, 3);
                $bomItem->operationAllocations()->create([
                    'bom_operation_id' => $operation->id,
                    'quantity' => $quantity,
                ]);
            }

            if (bccomp($total, (string) $item['quantity'], 3) !== 0) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.operation_allocations" => "مجموع توزيع خامة {$material->name} على العمليات يجب أن يساوي {$item['quantity']}.",
                ]);
            }
        }
    }
}
