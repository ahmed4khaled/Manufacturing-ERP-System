<?php

namespace App\Services;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\CutoverBatch;
use App\Models\CutoverOpeningBalance;
use App\Models\GoodsReceipt;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\ProductionOutput;
use App\Models\ProductVariant;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Services\Cutover\CutoverDateGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        private readonly InventoryCostingService $costing,
        private readonly CutoverDateGuard $cutoverGuard,
    ) {}

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
     * @return Collection<int, string>
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
            $variant->id => bcadd((string) ($stockByVariant[$variant->id] ?? '0'), '0', 3),
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

        $stockIn = (string) StockMovement::query()
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
        $stockOut = (string) StockMovement::query()
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
        $runningBalance = bcsub($stockIn, $stockOut, 3);

        foreach ($movements as $movement) {
            $runningBalance = $movement->direction === StockMovementDirection::IN
                ? bcadd($runningBalance, (string) $movement->quantity, 3)
                : bcsub($runningBalance, (string) $movement->quantity, 3);

            $movement->running_balance = $runningBalance;
        }

        return $movements;
    }

    public function validateAvailability(Collection $items): void
    {
        $requestedQuantities = $items
            ->groupBy('product_variant_id')
            ->map(fn (Collection $variantItems) => $variantItems->reduce(
                fn (string $total, $item) => bcadd($total, (string) $item->quantity, 3),
                '0.000',
            ));
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
                    'products' => "الرصيد غير كافٍ للمتغير {$variant?->sku}. المتاح: {$availableStock}. المطلوب: {$requestedQuantity}.",
                ]);
            }
        }
    }

    public function createOpeningStock(array $data): StockMovement
    {
        $this->cutoverGuard->assertLegacyOpeningAllowed();

        $movement = $this->createMovement($data + [
            'movement_type' => StockMovementType::OPENING,
            'direction' => StockMovementDirection::IN,
        ]);
        $this->costing->createUnvaluedProductLayer($movement);

        return $movement;
    }

    public function createCutoverOpening(CutoverOpeningBalance $line): array
    {
        $movement = $this->createMovement([
            'product_variant_id' => $line->product_variant_id,
            'movement_type' => StockMovementType::OPENING,
            'direction' => StockMovementDirection::IN,
            'quantity' => $line->quantity,
            'reference_type' => CutoverBatch::class,
            'reference_id' => $line->cutover_batch_id,
            'reference_number' => $line->layer_reference,
            'movement_date' => $line->batch->cutover_date,
            'notes' => $line->description ?: 'Cutover opening finished-goods layer',
            'user_id' => auth()->id(),
        ]);
        $layer = $this->costing->createOpeningProductLayer($movement, $line->unit_cost);

        return compact('movement', 'layer');
    }

    public function reverseCutoverOpening(CutoverOpeningBalance $line, string $date): StockMovement
    {
        $layer = ProductCostLayer::query()->lockForUpdate()->findOrFail($line->product_cost_layer_id);
        if ($layer->allocations()->exists() || bccomp((string) $layer->remaining_quantity, (string) $layer->original_quantity, 3) !== 0) {
            throw ValidationException::withMessages(['cutover' => "لا يمكن عكس {$line->layer_reference} بعد استهلاك طبقة FIFO."]);
        }
        $movement = $this->createMovement([
            'product_variant_id' => $line->product_variant_id,
            'movement_type' => StockMovementType::ADJUSTMENT_OUT,
            'direction' => StockMovementDirection::OUT,
            'quantity' => $line->quantity,
            'reference_type' => CutoverBatch::class,
            'reference_id' => $line->cutover_batch_id,
            'reference_number' => 'REV-'.$line->layer_reference,
            'movement_date' => $date,
            'notes' => 'عكس طبقة Cutover '.$line->layer_reference,
            'user_id' => auth()->id(),
        ]);
        ProductCostAllocation::query()->create([
            'product_cost_layer_id' => $layer->id,
            'stock_movement_id' => $movement->id,
            'reference_type' => CutoverBatch::class,
            'reference_id' => $line->cutover_batch_id,
            'direction' => 'out',
            'quantity' => $line->quantity,
            'unit_cost' => $layer->unit_cost,
            'total_cost' => $layer->total_original_cost,
        ]);
        $layer->update(['remaining_quantity' => 0]);

        return $movement;
    }

    public function createAdjustment(array $data): StockMovement
    {
        $direction = StockMovementDirection::from($data['direction']);

        if ($direction === StockMovementDirection::OUT) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($data['product_variant_id']);
            $availableStock = $this->getCurrentStock($variant);

            if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "الرصيد غير كافٍ. المتاح: {$availableStock}. المطلوب: {$data['quantity']}.",
                ]);
            }
        }

        $movement = $this->createMovement($data + [
            'movement_type' => $direction === StockMovementDirection::IN
                ? StockMovementType::ADJUSTMENT_IN
                : StockMovementType::ADJUSTMENT_OUT,
        ]);
        if ($direction === StockMovementDirection::IN) {
            $this->costing->createUnvaluedProductLayer($movement);
        } else {
            $this->costing->consumeProductMovement($movement);
        }

        return $movement;
    }

    public function createSalesMovements(SalesInvoice $invoice): void
    {
        $this->assertReferenceNotPosted(
            SalesInvoice::class,
            $invoice->id,
            StockMovementType::SALE,
        );

        $totalCogs = '0.00';
        $valued = true;
        foreach ($invoice->items as $item) {
            $movement = $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALE,
                'direction' => StockMovementDirection::OUT,
                'quantity' => $item->quantity,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'movement_date' => $invoice->invoice_date,
                'notes' => 'فاتورة مبيعات',
                'user_id' => auth()->id(),
            ]);
            $cost = $this->costing->consumeSale($item, $movement, $invoice);
            if ($cost === null) {
                $valued = false;
            } else {
                $totalCogs = bcadd($totalCogs, $cost, 2);
            }
        }
        $invoice->update(['total_cogs' => $valued ? $totalCogs : null]);
    }

    public function createSalesReversals(SalesInvoice $invoice): void
    {
        $this->assertReferenceNotPosted(
            SalesInvoice::class,
            $invoice->id,
            StockMovementType::SALE_REVERSAL,
        );

        foreach ($invoice->items as $item) {
            $movement = $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALE_REVERSAL,
                'direction' => StockMovementDirection::IN,
                'quantity' => $item->quantity,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'movement_date' => now()->toDateString(),
                'notes' => 'إلغاء فاتورة مبيعات',
                'user_id' => auth()->id(),
            ]);
            $this->costing->restoreSale($item, $movement, $invoice);
        }
    }

    public function createSalesReturnMovements(SalesReturn $salesReturn): void
    {
        $this->assertReferenceNotPosted(
            SalesReturn::class,
            $salesReturn->id,
            StockMovementType::SALES_RETURN,
        );

        $totalCogs = '0.00';
        $valued = true;
        foreach ($salesReturn->items as $item) {
            $movement = $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALES_RETURN,
                'direction' => StockMovementDirection::IN,
                'quantity' => $item->quantity,
                'reference_type' => SalesReturn::class,
                'reference_id' => $salesReturn->id,
                'reference_number' => $salesReturn->return_number,
                'movement_date' => $salesReturn->return_date,
                'notes' => 'مرتجع مبيعات',
                'user_id' => auth()->id(),
            ]);
            $cost = $this->costing->restoreSalesReturn($item, $movement, $salesReturn);
            if ($cost === null) {
                $valued = false;
            } else {
                $totalCogs = bcadd($totalCogs, $cost, 2);
            }
        }
        $salesReturn->update(['total_cogs' => $valued ? $totalCogs : null]);
    }

    public function createSalesReturnReversals(SalesReturn $salesReturn): void
    {
        $this->assertReferenceNotPosted(
            SalesReturn::class,
            $salesReturn->id,
            StockMovementType::SALES_RETURN_REVERSAL,
        );

        foreach ($salesReturn->items as $item) {
            $movement = $this->createMovement([
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => StockMovementType::SALES_RETURN_REVERSAL,
                'direction' => StockMovementDirection::OUT,
                'quantity' => $item->quantity,
                'reference_type' => SalesReturn::class,
                'reference_id' => $salesReturn->id,
                'reference_number' => $salesReturn->return_number,
                'movement_date' => now()->toDateString(),
                'notes' => 'إلغاء مرتجع مبيعات',
                'user_id' => auth()->id(),
            ]);
            $this->costing->reverseSalesReturn($item, $movement, $salesReturn);
        }
    }

    public function createPurchaseReceiptMovements(GoodsReceipt $receipt): void
    {
        foreach ($receipt->items as $item) {
            if (bccomp((string) $item->accepted_quantity, '0', 3) <= 0) {
                continue;
            }
            $this->createMovement(['product_variant_id' => $item->product_variant_id, 'movement_type' => StockMovementType::PURCHASE_RECEIPT, 'direction' => StockMovementDirection::IN, 'quantity' => $item->accepted_quantity, 'reference_type' => GoodsReceipt::class, 'reference_id' => $receipt->id, 'reference_number' => $receipt->receipt_number, 'movement_date' => $receipt->received_at->toDateString(), 'notes' => 'إذن استلام', 'user_id' => $receipt->received_by]);
        }
    }

    public function createPurchaseReturnMovements(PurchaseReturn $return): void
    {
        foreach ($return->items as $item) {
            $this->createMovement(['product_variant_id' => $item->product_variant_id, 'movement_type' => StockMovementType::PURCHASE_RETURN, 'direction' => StockMovementDirection::OUT, 'quantity' => $item->quantity, 'reference_type' => PurchaseReturn::class, 'reference_id' => $return->id, 'reference_number' => $return->return_number, 'movement_date' => $return->return_date->toDateString(), 'notes' => 'مرتجع مشتريات', 'user_id' => $return->created_by]);
        }
    }

    public function createProductionOutputMovement(ProductionOutput $output): StockMovement
    {
        $this->assertReferenceNotPosted(ProductionOutput::class, $output->id, StockMovementType::PRODUCTION_OUTPUT);

        $movement = $this->createMovement([
            'product_variant_id' => $output->product_variant_id,
            'movement_type' => StockMovementType::PRODUCTION_OUTPUT,
            'direction' => StockMovementDirection::IN,
            'quantity' => $output->quantity,
            'reference_type' => ProductionOutput::class,
            'reference_id' => $output->id,
            'reference_number' => $output->productionOrder->order_number,
            'movement_date' => $output->output_date,
            'notes' => 'إنتاج تام',
            'user_id' => $output->recorded_by,
        ]);
        $this->costing->createProvisionalProductLayer($output, $movement);

        return $movement;
    }

    private function createMovement(array $data): StockMovement
    {
        $this->cutoverGuard->assertAllowed((string) $data['movement_date'], $data['reference_type'] ?? null);
        $variant = ProductVariant::query()
            ->lockForUpdate()
            ->findOrFail($data['product_variant_id']);

        $direction = $data['direction'] instanceof StockMovementDirection
            ? $data['direction']
            : StockMovementDirection::from($data['direction']);

        if ($direction === StockMovementDirection::OUT) {
            $availableStock = $this->getCurrentStock($variant);

            if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة أكبر من الرصيد المتاح. المتاح: {$availableStock}. المطلوب: {$data['quantity']}.",
                ]);
            }
        }

        $movement = StockMovement::create($data);
        $this->syncLegacyQuantity($variant);

        return $movement;
    }

    private function syncLegacyQuantity(ProductVariant $variant): void
    {
        $variant->update(['quantity' => $this->getCurrentStock($variant)]);
    }

    private function assertReferenceNotPosted(
        string $referenceType,
        int $referenceId,
        StockMovementType $movementType,
    ): void {
        if (StockMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('movement_type', $movementType)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => 'تم ترحيل حركة المخزون لهذا المستند بالفعل.',
            ]);
        }
    }
}
