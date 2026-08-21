<?php

namespace App\Services;

use App\Models\Bom;
use Illuminate\Support\Collection;

class BomRequirementService
{
    /** @return Collection<int, array<string, mixed>> */
    public function calculate(Bom $bom, string $productionQuantity): Collection
    {
        $bom->loadMissing('items.material');

        return $bom->items->map(function ($item) use ($bom, $productionQuantity) {
            $perUnit = bcdiv((string) $item->quantity, (string) $bom->basis_quantity, 6);

            return [
                'bom_item' => $item, 'material' => $item->material,
                'standard_quantity' => bcmul($perUnit, $productionQuantity, 3),
            ];
        });
    }
}
