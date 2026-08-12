<?php

namespace App\Services;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function getCurrentStock(ProductVariant $variant): string
    {
        $stockIn = (string) $variant->stockMovements()
            ->where('direction', StockMovementDirection::IN)
            ->sum('quantity');
        $stockOut = (string) $variant->stockMovements()
            ->where('direction', StockMovementDirection::OUT)
            ->sum('quantity');

        return bcsub($stockIn, $stockOut, 3);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, int>
     */
    public function getCurrentStocks(Collection $variants): Collection
    {
        $stockByVariant = StockMovement::query()
            ->selectRaw(
                'product_variant_id, SUM(CASE WHEN direction = ? THEN quantity ELSE -quantity END) as current_stock',
                [StockMovementDirection::IN->value],
            )
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->groupBy('product_variant_id')
            ->pluck('current_stock', 'product_variant_id');

        return $variants->mapWithKeys(fn (ProductVariant $variant) => [
            $variant->id => number_format((float) ($stockByVariant[$variant->id] ?? 0), 3, '.', ''),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, StockMovement>  $movements
     */
    public function attachRunningBalances(LengthAwarePaginator $movements): LengthAwarePaginator
    {
        $firstMovement = $movements->first();

        if (! $firstMovement) {
            return $movements;
        }

        $stockIn = (int) StockMovement::query()
            ->where('product_variant_id', $firstMovement->product_variant_id)
            ->where(function ($query) use ($firstMovement) {
                $query->where('movement_date', '<', $firstMovement->movement_date)
                    ->orWhere(function ($query) use ($firstMovement) {
                        $query->where('movement_date', $firstMovement->movement_date)
                            ->where('id', '<', $firstMovement->id);
                    });
            })
            ->where('direction', StockMovementDirection::IN)
            ->sum('quantity');
        $stockOut = (int) StockMovement::query()
            ->where('product_variant_id', $firstMovement->product_variant_id)
            ->where(function ($query) use ($firstMovement) {
                $query->where('movement_date', '<', $firstMovement->movement_date)
                    ->orWhere(function ($query) use ($firstMovement) {
                        $query->where('movement_date', $firstMovement->movement_date)
                            ->where('id', '<', $firstMovement->id);
                    });
            })
            ->where('direction', StockMovementDirection::OUT)
            ->sum('quantity');
        $runningBalance = $stockIn - $stockOut;

        foreach ($movements as $movement) {
            $runningBalance += $movement->direction === StockMovementDirection::IN
                ? $movement->quantity
                : -$movement->quantity;

            $movement->running_balance = $runningBalance;
        }

        return $movements;
    }

    public function validateAvailability(Collection $items): void
    {
        $requestedQuantities = $items->groupBy('product_variant_id')->map->sum('quantity');
        $variants = ProductVariant::query()
            ->whereIn('id', $requestedQuantities->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requestedQuantities as $variantId => $requestedQuantity) {
            $variant = $variants->get($variantId);
            $availableStock = $variant ? $this->getCurrentStock($variant) : 0;

            if (bccomp($availableStock, (string) $requestedQuantity, 3) < 0) {
                throw ValidationException::withMessages([
                    'products' => "Insufficient stock for variant {$variant?->sku}. Available: {$availableStock}. Requested: {$requestedQuantity}.",
                ]);
            }
        }
    }

    public function createOpeningStock(array $data): StockMovement
    {
        return $this->createMovement($data + [
            'movement_type' => StockMovementType::OPENING,
            'direction' => StockMovementDirection::IN,
        ]);
    }

    public function createAdjustment(array $data): StockMovement
    {
        $direction = StockMovementDirection::from($data['direction']);

        if ($direction === StockMovementDirection::OUT) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($data['product_variant_id']);
            $availableStock = $this->getCurrentStock($variant);

            if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock. Available: {$availableStock}. Requested: {$data['quantity']}.",
                ]);
            }
        }

        return $this->createMovement($data + [
            'movement_type' => $direction === StockMovementDirection::IN
                ? StockMovementType::ADJUSTMENT_IN
                : StockMovementType::ADJUSTMENT_OUT,
        ]);
    }

    public function createSalesMovements(SalesInvoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALE,
                'direction' => StockMovementDirection::OUT,
                'quantity' => $item->quantity,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'movement_date' => $invoice->invoice_date,
                'notes' => 'Sales Invoice',
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function createSalesReversals(SalesInvoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALE_REVERSAL,
                'direction' => StockMovementDirection::IN,
                'quantity' => $item->quantity,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'movement_date' => now()->toDateString(),
                'notes' => 'Sales Invoice Cancellation',
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function createPurchaseReceiptMovements(GoodsReceipt $receipt): void
    {
        foreach ($receipt->items as $item) {
            if (bccomp((string) $item->accepted_quantity, '0', 3) <= 0) {
                continue;
            }
            $this->createMovement(['product_variant_id'=>$item->product_variant_id,'movement_type'=>StockMovementType::PURCHASE_RECEIPT,'direction'=>StockMovementDirection::IN,'quantity'=>$item->accepted_quantity,'reference_type'=>GoodsReceipt::class,'reference_id'=>$receipt->id,'reference_number'=>$receipt->receipt_number,'movement_date'=>$receipt->received_at->toDateString(),'notes'=>'Goods Receipt','user_id'=>$receipt->received_by]);
        }
    }

    public function createPurchaseReturnMovements(PurchaseReturn $return): void
    {
        foreach ($return->items as $item) {
            $this->createMovement(['product_variant_id'=>$item->product_variant_id,'movement_type'=>StockMovementType::PURCHASE_RETURN,'direction'=>StockMovementDirection::OUT,'quantity'=>$item->quantity,'reference_type'=>PurchaseReturn::class,'reference_id'=>$return->id,'reference_number'=>$return->return_number,'movement_date'=>$return->return_date->toDateString(),'notes'=>'Purchase Return','user_id'=>$return->created_by]);
        }
    }

    private function createMovement(array $data): StockMovement
    {
        $movement = StockMovement::create($data);
        $this->syncLegacyQuantity($movement);

        return $movement;
    }

    private function syncLegacyQuantity(\App\Models\StockMovement $movement): void
    {
        $variant = $movement->productVariant->fresh();

        $current = (string) ($variant->quantity ?? '0');
        $delta = (string) $movement->quantity;

        if ($movement->direction === StockMovementDirection::IN) {
            $new = bcadd($current, $delta, 3);
        } else {
            $new = bcsub($current, $delta, 3);
        }

        $variant->update(['quantity' => $new]);
    }
}
