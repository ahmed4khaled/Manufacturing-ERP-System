<?php

namespace App\Actions\Product;

use App\Enums\BomStatus;
use App\Models\Bom;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProductAction
{
    public function handle(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            if (array_key_exists('base_cost_price', $data)) {
                $activeBom = Bom::query()
                    ->where('product_id', $product->id)
                    ->where('status', BomStatus::ACTIVE)
                    ->withSum('operations', 'unit_cost')
                    ->latest('activated_at')
                    ->first();
                $operationCost = (string) ($activeBom?->operations_sum_unit_cost ?? 0);
                $data['cost_price'] = bcadd((string) $data['base_cost_price'], $operationCost, 2);
            }
            $product->update($data);

            return $product->fresh();
        });
    }
}
