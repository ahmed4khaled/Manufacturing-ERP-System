<?php

namespace App\Actions\Procurement;

use App\Models\{PurchaseReturn, StockMovement, ProductVariant};
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPurchaseReturnAction
{
    public function __construct(private StockService $stock) {}
    public function handle(PurchaseReturn $return): PurchaseReturn
    {
        return DB::transaction(function () use ($return) {
            $return = PurchaseReturn::query()->lockForUpdate()->findOrFail($return->id);
            if ($return->status !== 'draft') throw ValidationException::withMessages(['status' => 'Only draft returns can be confirmed.']);
            if (StockMovement::where('reference_type', PurchaseReturn::class)->where('reference_id', $return->id)->exists()) throw ValidationException::withMessages(['status' => 'Return inventory is already posted.']);
            $return->load('items');
            foreach ($return->items as $item) {
                $v = ProductVariant::query()->lockForUpdate()->findOrFail($item->product_variant_id);
                $available = $this->stock->getCurrentStock($v);
                // fallback to variant legacy `quantity` when no stock movements exist
                if (bccomp($available, '0', 3) === 0) {
                    $available = (string) $v->quantity;
                }

                if (bccomp($available, (string) $item->quantity, 3) < 0) {
                    throw ValidationException::withMessages(['items' => 'Return quantity exceeds available stock.']);
                }
            }
            $return->update(['status' => 'confirmed']);
            $this->stock->createPurchaseReturnMovements($return);
            return $return->fresh('items');
        });
    }
}
