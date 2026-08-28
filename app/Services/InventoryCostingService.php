<?php

namespace App\Services;

use App\Enums\ProductionMaterialTransactionType;
use App\Enums\StockMovementDirection;
use App\Models\GoodsReceiptItem;
use App\Models\MaterialCostAllocation;
use App\Models\MaterialCostLayer;
use App\Models\MaterialStockMovement;
use App\Models\ProductCostAllocation;
use App\Models\ProductCostLayer;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryCostingService
{
    public function createMaterialReceiptLayer(GoodsReceiptItem $item, MaterialStockMovement $movement): MaterialCostLayer
    {
        return MaterialCostLayer::query()->firstOrCreate(
            ['material_stock_movement_id' => $movement->id],
            [
                'material_id' => $item->material_id,
                'goods_receipt_id' => $item->goods_receipt_id,
                'goods_receipt_item_id' => $item->id,
                'layer_date' => $movement->movement_date,
                'original_quantity' => $movement->quantity,
                'remaining_quantity' => $movement->quantity,
                'unit_cost' => $item->unit_cost,
                'total_original_cost' => bcmul((string) $movement->quantity, (string) $item->unit_cost, 2),
            ],
        );
    }

    public function createUnvaluedMaterialLayer(MaterialStockMovement $movement): MaterialCostLayer
    {
        return MaterialCostLayer::query()->firstOrCreate(
            ['material_stock_movement_id' => $movement->id],
            [
                'material_id' => $movement->material_id,
                'layer_date' => $movement->movement_date,
                'original_quantity' => $movement->quantity,
                'remaining_quantity' => $movement->quantity,
                'unit_cost' => null,
                'total_original_cost' => null,
            ],
        );
    }

    public function consumeMaterialIssue(ProductionMaterialTransaction $transaction, MaterialStockMovement $movement): ?string
    {
        $this->ensureLegacyMaterialCoverage($transaction->material_id, (string) $transaction->quantity);
        $allocations = $this->consumeMaterialLayers(
            $transaction->material_id,
            (string) $transaction->quantity,
            $movement,
            ProductionMaterialTransaction::class,
            $transaction->id,
        );
        $cost = $this->allocationCost($allocations);
        $transaction->update(['actual_cost' => $cost]);

        return $cost;
    }

    public function consumeMaterialMovement(MaterialStockMovement $movement): ?string
    {
        $this->ensureLegacyMaterialCoverage($movement->material_id, (string) $movement->quantity);

        return $this->consumeMaterialCollection(
            MaterialCostLayer::query()->where('material_id', $movement->material_id)->where('remaining_quantity', '>', 0)->orderBy('layer_date')->orderBy('id')->lockForUpdate()->get(),
            (string) $movement->quantity,
            $movement,
            MaterialStockMovement::class,
            $movement->id,
        );
    }

    public function restoreMaterialReturn(ProductionMaterialTransaction $transaction, MaterialStockMovement $movement): ?string
    {
        $needed = (string) $transaction->quantity;
        $restored = '0.000';
        $total = '0.00';
        $valued = true;
        $issueIds = ProductionMaterialTransaction::query()
            ->where('work_assignment_material_id', $transaction->work_assignment_material_id)
            ->where('type', ProductionMaterialTransactionType::ISSUE)
            ->pluck('id');
        $sources = MaterialCostAllocation::query()
            ->where('reference_type', ProductionMaterialTransaction::class)
            ->whereIn('reference_id', $issueIds)
            ->where('direction', 'out')
            ->whereNull('reversed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($sources as $source) {
            $available = bcsub((string) $source->quantity, (string) $source->restored_quantity, 3);
            if (bccomp($available, '0', 3) <= 0) {
                continue;
            }
            $quantity = bccomp(bcsub($needed, $restored, 3), $available, 3) < 0 ? bcsub($needed, $restored, 3) : $available;
            $layer = MaterialCostLayer::query()->lockForUpdate()->findOrFail($source->material_cost_layer_id);
            $layer->increment('remaining_quantity', (float) $quantity);
            $source->increment('restored_quantity', (float) $quantity);
            $lineCost = $source->unit_cost === null ? null : bcmul($quantity, (string) $source->unit_cost, 2);
            MaterialCostAllocation::create([
                'material_cost_layer_id' => $layer->id, 'material_stock_movement_id' => $movement->id,
                'reference_type' => ProductionMaterialTransaction::class, 'reference_id' => $transaction->id,
                'direction' => 'in', 'source_allocation_id' => $source->id, 'quantity' => $quantity,
                'unit_cost' => $source->unit_cost, 'total_cost' => $lineCost,
            ]);
            if ($lineCost === null) {
                $valued = false;
            } else {
                $total = bcadd($total, $lineCost, 2);
            }
            $restored = bcadd($restored, $quantity, 3);
            if (bccomp($restored, $needed, 3) === 0) {
                break;
            }
        }
        if (bccomp($restored, $needed, 3) !== 0) {
            throw ValidationException::withMessages(['quantity' => 'كمية المرتجع تتجاوز الكمية المصروفة المتاحة للإرجاع.']);
        }
        $cost = $valued ? $total : null;
        $transaction->update(['actual_cost' => $cost]);

        return $cost;
    }

    public function consumePurchaseReturn(PurchaseReturnItem $item, MaterialStockMovement $movement, PurchaseReturn $return): ?string
    {
        $layers = MaterialCostLayer::query()
            ->where('goods_receipt_id', $return->goods_receipt_id)
            ->where('material_id', $item->material_id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('layer_date')->orderBy('id')->lockForUpdate()->get();

        return $this->consumeMaterialCollection($layers, (string) $item->quantity, $movement, PurchaseReturn::class, $return->id);
    }

    public function createProvisionalProductLayer(ProductionOutput $output, StockMovement $movement): ProductCostLayer
    {
        return ProductCostLayer::query()->firstOrCreate(
            ['production_output_id' => $output->id],
            [
                'product_variant_id' => $output->product_variant_id, 'production_order_id' => $output->production_order_id,
                'stock_movement_id' => $movement->id, 'layer_date' => $movement->movement_date,
                'original_quantity' => $output->quantity, 'remaining_quantity' => $output->quantity,
                'unit_cost' => null, 'total_original_cost' => null, 'is_final' => false,
            ],
        );
    }

    public function createUnvaluedProductLayer(StockMovement $movement): ProductCostLayer
    {
        return ProductCostLayer::query()->firstOrCreate(
            ['stock_movement_id' => $movement->id],
            [
                'product_variant_id' => $movement->product_variant_id, 'layer_date' => $movement->movement_date,
                'original_quantity' => $movement->quantity, 'remaining_quantity' => $movement->quantity,
                'unit_cost' => null, 'total_original_cost' => null, 'is_final' => true,
            ],
        );
    }

    public function currentProductionMaterialCost(ProductionOrder $order): ?string
    {
        $transactions = ProductionMaterialTransaction::query()
            ->where('production_order_id', $order->id)
            ->whereIn('type', [
                ProductionMaterialTransactionType::ISSUE,
                ProductionMaterialTransactionType::RETURN,
            ])
            ->get(['type', 'actual_cost']);

        if ($transactions->contains(fn (ProductionMaterialTransaction $transaction) => $transaction->actual_cost === null)) {
            return null;
        }

        $issues = $transactions
            ->where('type', ProductionMaterialTransactionType::ISSUE)
            ->reduce(fn (string $total, ProductionMaterialTransaction $transaction) => bcadd($total, (string) $transaction->actual_cost, 2), '0.00');
        $returns = $transactions
            ->where('type', ProductionMaterialTransactionType::RETURN)
            ->reduce(fn (string $total, ProductionMaterialTransaction $transaction) => bcadd($total, (string) $transaction->actual_cost, 2), '0.00');

        return bcsub($issues, $returns, 2);
    }

    /** @return array{actual_material_cost:?string,actual_operation_cost:string,total:?string,unit:?string,variance:?string} */
    public function finalizeProductionCost(ProductionOrder $order, string $goodOutput): array
    {
        $transactions = ProductionMaterialTransaction::query()->where('production_order_id', $order->id)->get();
        $hasUnvalued = $transactions->whereIn('type', [ProductionMaterialTransactionType::ISSUE, ProductionMaterialTransactionType::RETURN])
            ->contains(fn ($transaction) => $transaction->actual_cost === null);
        $issues = (string) $transactions->where('type', ProductionMaterialTransactionType::ISSUE)->sum('actual_cost');
        $returns = (string) $transactions->where('type', ProductionMaterialTransactionType::RETURN)->sum('actual_cost');
        $actualMaterial = $hasUnvalued ? null : bcsub($issues, $returns, 2);
        $actualOperations = bcmul((string) $order->operations_unit_cost, (string) $order->planned_quantity, 2);
        $total = $actualMaterial === null ? null : bcadd(bcadd($actualMaterial, $actualOperations, 2), (string) $order->manufacturing_overhead_cost, 2);
        $unit = $total === null ? null : bcdiv($total, $goodOutput, 4);
        $variance = $total === null ? null : bcsub($total, (string) $order->estimated_total_cost, 2);

        return ['actual_material_cost' => $actualMaterial, 'actual_operation_cost' => $actualOperations, 'total' => $total, 'unit' => $unit, 'variance' => $variance];
    }

    public function finalizeProductLayers(ProductionOrder $order, string $unitCost): void
    {
        $layers = ProductCostLayer::query()->where('production_order_id', $order->id)->lockForUpdate()->get();
        foreach ($layers as $layer) {
            if ($layer->allocations()->where('direction', 'out')->whereNull('reversed_at')->exists()) {
                throw ValidationException::withMessages(['costing' => 'لا يمكن إنهاء تكلفة أمر إنتاج تم بيع مخرجاته قبل اعتماد التكلفة النهائية.']);
            }
            $layer->update(['unit_cost' => $unitCost, 'total_original_cost' => bcmul((string) $layer->original_quantity, $unitCost, 2), 'is_final' => true]);
            $layer->productionOutput()->update(['unit_cost' => $unitCost, 'total_cost' => bcmul((string) $layer->original_quantity, $unitCost, 2)]);
        }
    }

    public function finalizeUnvaluedProductLayers(ProductionOrder $order): void
    {
        ProductCostLayer::query()->where('production_order_id', $order->id)->lockForUpdate()->update(['is_final' => true]);
    }

    public function consumeSale(SalesInvoiceItem $item, StockMovement $movement, SalesInvoice $invoice): ?string
    {
        $this->ensureLegacyProductCoverage($item->product_variant_id, (string) $item->quantity);
        $layers = ProductCostLayer::query()->where('product_variant_id', $item->product_variant_id)
            ->where('remaining_quantity', '>', 0)->where('is_final', true)
            ->orderBy('layer_date')->orderBy('id')->lockForUpdate()->get();

        return $this->consumeProductCollection($layers, (string) $item->quantity, $movement, SalesInvoice::class, $invoice->id);
    }

    public function consumeProductMovement(StockMovement $movement): ?string
    {
        $this->ensureLegacyProductCoverage($movement->product_variant_id, (string) $movement->quantity);

        return $this->consumeProductCollection(
            ProductCostLayer::query()->where('product_variant_id', $movement->product_variant_id)->where('remaining_quantity', '>', 0)->where('is_final', true)->orderBy('layer_date')->orderBy('id')->lockForUpdate()->get(),
            (string) $movement->quantity,
            $movement,
            StockMovement::class,
            $movement->id,
        );
    }

    public function restoreSale(SalesInvoiceItem $item, StockMovement $movement, SalesInvoice $invoice): ?string
    {
        return $this->restoreProductAllocations(SalesInvoice::class, $invoice->id, $item->product_variant_id, (string) $item->quantity, $movement, SalesInvoice::class, $invoice->id);
    }

    public function restoreSalesReturn(SalesReturnItem $item, StockMovement $movement, SalesReturn $return): ?string
    {
        return $this->restoreProductAllocations(SalesInvoice::class, $return->sales_invoice_id, $item->product_variant_id, (string) $item->quantity, $movement, SalesReturn::class, $return->id);
    }

    public function reverseSalesReturn(SalesReturnItem $item, StockMovement $movement, SalesReturn $return): ?string
    {
        $restores = ProductCostAllocation::query()->where('reference_type', SalesReturn::class)->where('reference_id', $return->id)
            ->where('direction', 'in')->whereHas('layer', fn ($q) => $q->where('product_variant_id', $item->product_variant_id))
            ->whereNull('reversed_at')->lockForUpdate()->get();
        $quantity = '0.000';
        $total = '0.00';
        $valued = true;
        foreach ($restores as $restore) {
            $layer = ProductCostLayer::query()->lockForUpdate()->findOrFail($restore->product_cost_layer_id);
            if (bccomp((string) $layer->remaining_quantity, (string) $restore->quantity, 3) < 0) {
                throw ValidationException::withMessages(['quantity' => 'تم استهلاك مخزون المرتجع ولا يمكن إلغاؤه.']);
            }
            $layer->decrement('remaining_quantity', (float) $restore->quantity);
            $source = ProductCostAllocation::query()->lockForUpdate()->findOrFail($restore->source_allocation_id);
            $source->decrement('restored_quantity', (float) $restore->quantity);
            $restore->update(['reversed_at' => now()]);
            ProductCostAllocation::create([
                'product_cost_layer_id' => $layer->id, 'stock_movement_id' => $movement->id,
                'reference_type' => SalesReturn::class, 'reference_id' => $return->id, 'direction' => 'out',
                'source_allocation_id' => $restore->id, 'quantity' => $restore->quantity,
                'unit_cost' => $restore->unit_cost, 'total_cost' => $restore->total_cost,
            ]);
            $quantity = bcadd($quantity, (string) $restore->quantity, 3);
            if ($restore->total_cost === null) {
                $valued = false;
            } else {
                $total = bcadd($total, (string) $restore->total_cost, 2);
            }
        }
        if (bccomp($quantity, (string) $item->quantity, 3) !== 0) {
            throw ValidationException::withMessages(['costing' => 'تعذر عكس تكلفة مرتجع المبيعات بالكامل.']);
        }

        return $valued ? $total : null;
    }

    private function consumeMaterialLayers(int $materialId, string $quantity, MaterialStockMovement $movement, string $referenceType, int $referenceId): Collection
    {
        $layers = MaterialCostLayer::query()->where('material_id', $materialId)->where('remaining_quantity', '>', 0)
            ->orderBy('layer_date')->orderBy('id')->lockForUpdate()->get();
        $this->consumeMaterialCollection($layers, $quantity, $movement, $referenceType, $referenceId);

        return MaterialCostAllocation::query()->where('material_stock_movement_id', $movement->id)->where('direction', 'out')->get();
    }

    private function consumeMaterialCollection(Collection $layers, string $needed, MaterialStockMovement $movement, string $referenceType, int $referenceId): ?string
    {
        $consumed = '0.000';
        $total = '0.00';
        $valued = true;
        foreach ($layers as $layer) {
            $take = bccomp(bcsub($needed, $consumed, 3), (string) $layer->remaining_quantity, 3) < 0 ? bcsub($needed, $consumed, 3) : (string) $layer->remaining_quantity;
            if (bccomp($take, '0', 3) <= 0) {
                continue;
            }
            $cost = $layer->unit_cost === null ? null : bcmul($take, (string) $layer->unit_cost, 2);
            $layer->decrement('remaining_quantity', (float) $take);
            MaterialCostAllocation::create(['material_cost_layer_id' => $layer->id, 'material_stock_movement_id' => $movement->id, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'direction' => 'out', 'quantity' => $take, 'unit_cost' => $layer->unit_cost, 'total_cost' => $cost]);
            if ($cost === null) {
                $valued = false;
            } else {
                $total = bcadd($total, $cost, 2);
            }
            $consumed = bcadd($consumed, $take, 3);
            if (bccomp($consumed, $needed, 3) === 0) {
                break;
            }
        }
        if (bccomp($consumed, $needed, 3) !== 0) {
            throw ValidationException::withMessages(['costing' => 'طبقات تكلفة الخامة لا تغطي كمية المخزون المطلوبة.']);
        }

        return $valued ? $total : null;
    }

    private function consumeProductCollection(Collection $layers, string $needed, StockMovement $movement, string $referenceType, int $referenceId): ?string
    {
        $consumed = '0.000';
        $total = '0.00';
        $valued = true;
        foreach ($layers as $layer) {
            $take = bccomp(bcsub($needed, $consumed, 3), (string) $layer->remaining_quantity, 3) < 0 ? bcsub($needed, $consumed, 3) : (string) $layer->remaining_quantity;
            if (bccomp($take, '0', 3) <= 0) {
                continue;
            }
            if ($layer->unit_cost === null) {
                throw ValidationException::withMessages([
                    'costing' => 'لا يمكن صرف أو بيع مخزون منتج من طبقة تكلفة غير مُقيّمة.',
                ]);
            }
            $cost = $layer->unit_cost === null ? null : bcmul($take, (string) $layer->unit_cost, 2);
            $layer->decrement('remaining_quantity', (float) $take);
            ProductCostAllocation::create(['product_cost_layer_id' => $layer->id, 'stock_movement_id' => $movement->id, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'direction' => 'out', 'quantity' => $take, 'unit_cost' => $layer->unit_cost, 'total_cost' => $cost]);
            if ($cost === null) {
                $valued = false;
            } else {
                $total = bcadd($total, $cost, 2);
            }
            $consumed = bcadd($consumed, $take, 3);
            if (bccomp($consumed, $needed, 3) === 0) {
                break;
            }
        }
        if (bccomp($consumed, $needed, 3) !== 0) {
            throw ValidationException::withMessages(['costing' => 'لا توجد طبقات تكلفة نهائية كافية للمتغير المباع. أغلق أمر الإنتاج أولًا أو قيّم الرصيد القديم.']);
        }

        return $valued ? $total : null;
    }

    private function restoreProductAllocations(string $sourceType, int $sourceId, int $variantId, string $needed, StockMovement $movement, string $referenceType, int $referenceId): ?string
    {
        $sources = ProductCostAllocation::query()->where('reference_type', $sourceType)->where('reference_id', $sourceId)->where('direction', 'out')
            ->whereHas('layer', fn ($q) => $q->where('product_variant_id', $variantId))->whereNull('reversed_at')->orderByDesc('id')->lockForUpdate()->get();
        $restored = '0.000';
        $total = '0.00';
        $valued = true;
        foreach ($sources as $source) {
            $available = bcsub((string) $source->quantity, (string) $source->restored_quantity, 3);
            if (bccomp($available, '0', 3) <= 0) {
                continue;
            }
            $take = bccomp(bcsub($needed, $restored, 3), $available, 3) < 0 ? bcsub($needed, $restored, 3) : $available;
            $layer = ProductCostLayer::query()->lockForUpdate()->findOrFail($source->product_cost_layer_id);
            $layer->increment('remaining_quantity', (float) $take);
            $source->increment('restored_quantity', (float) $take);
            $cost = $source->unit_cost === null ? null : bcmul($take, (string) $source->unit_cost, 2);
            ProductCostAllocation::create(['product_cost_layer_id' => $layer->id, 'stock_movement_id' => $movement->id, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'direction' => 'in', 'source_allocation_id' => $source->id, 'quantity' => $take, 'unit_cost' => $source->unit_cost, 'total_cost' => $cost]);
            if ($cost === null) {
                $valued = false;
            } else {
                $total = bcadd($total, $cost, 2);
            }
            $restored = bcadd($restored, $take, 3);
            if (bccomp($restored, $needed, 3) === 0) {
                break;
            }
        }
        if (bccomp($restored, $needed, 3) !== 0) {
            throw ValidationException::withMessages(['costing' => 'تعذر استعادة تكلفة الكمية المرتجعة من البيع الأصلي.']);
        }

        return $valued ? $total : null;
    }

    private function ensureLegacyMaterialCoverage(int $materialId, string $outgoing): void
    {
        $physicalAfter = $this->physicalMaterialStock($materialId);
        $requiredBefore = bcadd($physicalAfter, $outgoing, 3);
        $layers = (string) MaterialCostLayer::query()->where('material_id', $materialId)->sum('remaining_quantity');
        $gap = bcsub($requiredBefore, $layers, 3);
        if (bccomp($gap, '0', 3) > 0) {
            MaterialCostLayer::create(['material_id' => $materialId, 'layer_date' => '1900-01-01', 'original_quantity' => $gap, 'remaining_quantity' => $gap, 'unit_cost' => null, 'total_original_cost' => null]);
        }
    }

    private function ensureLegacyProductCoverage(int $variantId, string $outgoing): void
    {
        $physicalAfter = $this->physicalProductStock($variantId);
        $requiredBefore = bcadd($physicalAfter, $outgoing, 3);
        $layers = (string) ProductCostLayer::query()->where('product_variant_id', $variantId)->sum('remaining_quantity');
        $gap = bcsub($requiredBefore, $layers, 3);
        if (bccomp($gap, '0', 3) > 0) {
            ProductCostLayer::create(['product_variant_id' => $variantId, 'layer_date' => '1900-01-01', 'original_quantity' => $gap, 'remaining_quantity' => $gap, 'unit_cost' => null, 'total_original_cost' => null, 'is_final' => true]);
        }
    }

    private function physicalMaterialStock(int $materialId): string
    {
        $in = (string) MaterialStockMovement::query()->where('material_id', $materialId)->where('direction', StockMovementDirection::IN)->sum('quantity');
        $out = (string) MaterialStockMovement::query()->where('material_id', $materialId)->where('direction', StockMovementDirection::OUT)->sum('quantity');

        return bcsub($in, $out, 3);
    }

    private function physicalProductStock(int $variantId): string
    {
        $in = (string) StockMovement::query()->where('product_variant_id', $variantId)->where('direction', StockMovementDirection::IN)->sum('quantity');
        $out = (string) StockMovement::query()->where('product_variant_id', $variantId)->where('direction', StockMovementDirection::OUT)->sum('quantity');

        return bcsub($in, $out, 3);
    }

    private function allocationCost(Collection $allocations): ?string
    {
        if ($allocations->contains(fn ($allocation) => $allocation->total_cost === null)) {
            return null;
        }

        return $allocations->reduce(fn ($total, $allocation) => bcadd($total, (string) $allocation->total_cost, 2), '0.00');
    }
}
