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

            return $bom->fresh(['product', 'productVariant.color', 'productVariant.size', 'items', 'operations']);
        });
    }

    public function replaceDetails(Bom $bom, array $data, $materials = null, $operations = null): void
    {
        $materials ??= Material::query()->whereIn('id', collect($data['items'])->pluck('material_id'))->get()->keyBy('id');
        $operations ??= ManufacturingOperation::query()->whereIn('id', collect($data['operations'])->pluck('manufacturing_operation_id'))->get()->keyBy('id');
        $bom->items()->delete();
        $bom->operations()->delete();
        foreach ($data['items'] as $item) {
            $material = $materials->get($item['material_id']);
            $bom->items()->create(['material_id' => $material->id, 'material_code' => $material->code, 'material_name' => $material->name, 'unit' => $material->unit->value, 'quantity' => $item['quantity']]);
        }
        foreach (array_values($data['operations']) as $index => $item) {
            $operation = $operations->get($item['manufacturing_operation_id']);
            $bom->operations()->create(['manufacturing_operation_id' => $operation->id, 'operation_name' => $operation->name, 'sequence' => $index + 1, 'notes' => $item['notes'] ?? null]);
        }
    }
}
