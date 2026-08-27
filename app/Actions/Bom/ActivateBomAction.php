<?php

namespace App\Actions\Bom;

use App\Enums\BomStatus;
use App\Models\Bom;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateBomAction
{
    public function handle(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            $bom = Bom::query()->with(['items', 'operations'])->lockForUpdate()->findOrFail($bom->id);
            if ($bom->status === BomStatus::ACTIVE) {
                return $bom;
            }
            if ($bom->status !== BomStatus::DRAFT || $bom->items->isEmpty() || $bom->operations->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تفعيل BOM إلا إذا كان مسودة مكتملة.']);
            }
            Bom::query()->where('product_variant_id', $bom->product_variant_id)->where('status', BomStatus::ACTIVE)->lockForUpdate()->update(['status' => BomStatus::RETIRED]);
            $bom->update(['status' => BomStatus::ACTIVE, 'activated_by' => auth()->id(), 'activated_at' => now()]);
            $operationsUnitCost = $bom->operations->reduce(
                fn ($total, $operation) => bcadd($total, (string) $operation->unit_cost, 2),
                '0.00',
            );
            $product = Product::query()->lockForUpdate()->findOrFail($bom->product_id);
            $product->update([
                'cost_price' => bcadd((string) $product->base_cost_price, $operationsUnitCost, 2),
            ]);

            return $bom->fresh();
        });
    }
}
