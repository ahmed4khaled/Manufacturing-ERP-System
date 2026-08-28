<?php

namespace App\Services;

use App\Models\Bom;

class BomCostService
{
    public function standardMaterialUnitCost(Bom $bom): ?string
    {
        $bom->loadMissing('items');

        if ($bom->items->contains(fn ($item) => $item->standard_unit_cost === null)) {
            return null;
        }

        $basisQuantity = (string) $bom->basis_quantity;
        if (bccomp($basisQuantity, '0', 3) <= 0) {
            return null;
        }

        $basisCost = $bom->items->reduce(
            fn (string $total, $item) => bcadd(
                $total,
                bcmul((string) $item->quantity, (string) $item->standard_unit_cost, 4),
                4,
            ),
            '0.0000',
        );

        return bcdiv($basisCost, $basisQuantity, 4);
    }

    /** @return array{unit_cost:string,source:string} */
    public function resolveStandardMaterialUnitCost(Bom $bom, string $legacyFallback): array
    {
        $bomCost = $this->standardMaterialUnitCost($bom);

        return $bomCost === null
            ? ['unit_cost' => bcadd($legacyFallback, '0', 4), 'source' => 'product_base_fallback']
            : ['unit_cost' => $bomCost, 'source' => 'bom'];
    }
}
