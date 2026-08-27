<?php

namespace App\Actions\Bom;

use App\Enums\BomStatus;
use App\Models\Bom;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBomAction
{
    public function __construct(private readonly CreateBomAction $creator) {}

    public function handle(Bom $bom, array $data): Bom
    {
        return DB::transaction(function () use ($bom, $data) {
            $bom = Bom::query()->lockForUpdate()->findOrFail($bom->id);
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($data['product_variant_id']);
            if ((int) $variant->product_id !== (int) $data['product_id']) {
                throw ValidationException::withMessages(['product_variant_id' => 'المتغير المحدد لا يتبع المنتج المحدد.']);
            }

            if ($bom->status === BomStatus::DRAFT) {
                $bom->update(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => $data['basis_quantity'], 'notes' => $data['notes'] ?? null]);
                $this->creator->replaceDetails($bom, $data);

                return $bom->fresh(['items.operationAllocations', 'operations']);
            }
            if ($bom->status !== BomStatus::ACTIVE) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تعديل إصدار BOM متوقف.']);
            }

            $version = (int) Bom::query()->where('bom_number', $bom->bom_number)->lockForUpdate()->max('version') + 1;
            $next = Bom::create(['bom_number' => $bom->bom_number, 'version' => $version, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'basis_quantity' => $data['basis_quantity'], 'notes' => $data['notes'] ?? null, 'status' => BomStatus::DRAFT, 'created_by' => auth()->id()]);
            $this->creator->replaceDetails($next, $data);

            return $next->fresh(['items.operationAllocations', 'operations']);
        });
    }
}
