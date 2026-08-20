<?php

namespace App\Services;

use App\Enums\MaterialStockMovementType;
use App\Enums\StockMovementDirection;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaterialStockService
{
    public function getCurrentStock(Material $material): string
    {
        $stockIn = (string) $material->stockMovements()
            ->where('direction', StockMovementDirection::IN)
            ->sum('quantity');
        $stockOut = (string) $material->stockMovements()
            ->where('direction', StockMovementDirection::OUT)
            ->sum('quantity');

        return bcsub($stockIn, $stockOut, 3);
    }

    /**
     * @param  Collection<int, Material>  $materials
     * @return Collection<int, string>
     */
    public function getCurrentStocks(Collection $materials): Collection
    {
        $stockByMaterial = MaterialStockMovement::query()
            ->selectRaw(
                'material_id, SUM(CASE WHEN direction = ? THEN quantity ELSE -quantity END) as current_stock',
                [StockMovementDirection::IN->value],
            )
            ->whereIn('material_id', $materials->pluck('id'))
            ->groupBy('material_id')
            ->pluck('current_stock', 'material_id');

        return $materials->mapWithKeys(fn (Material $material) => [
            $material->id => number_format((float) ($stockByMaterial[$material->id] ?? 0), 3, '.', ''),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, MaterialStockMovement>  $movements
     */
    public function attachRunningBalances(LengthAwarePaginator $movements): LengthAwarePaginator
    {
        $firstMovement = $movements->first();

        if (! $firstMovement) {
            return $movements;
        }

        $priorQuery = fn () => MaterialStockMovement::query()
            ->where('material_id', $firstMovement->material_id)
            ->where(function ($query) use ($firstMovement) {
                $query->where('movement_date', '<', $firstMovement->movement_date)
                    ->orWhere(function ($query) use ($firstMovement) {
                        $query->where('movement_date', $firstMovement->movement_date)
                            ->where('id', '<', $firstMovement->id);
                    });
            });

        $stockIn = (string) $priorQuery()
            ->where('direction', StockMovementDirection::IN)
            ->sum('quantity');
        $stockOut = (string) $priorQuery()
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
            ->groupBy('material_id')
            ->map(fn (Collection $materialItems) => $materialItems->reduce(
                fn (string $total, $item) => bcadd($total, (string) $item->quantity, 3),
                '0.000',
            ));
        $materials = Material::query()
            ->whereIn('id', $requestedQuantities->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requestedQuantities as $materialId => $requestedQuantity) {
            $material = $materials->get($materialId);
            $availableStock = $material ? $this->getCurrentStock($material) : '0';

            if (bccomp($availableStock, (string) $requestedQuantity, 3) < 0) {
                throw ValidationException::withMessages([
                    'materials' => "الرصيد غير كافٍ للخامة {$material?->code}. المتاح: {$availableStock}. المطلوب: {$requestedQuantity}.",
                ]);
            }
        }
    }

    public function createOpeningStock(array $data): MaterialStockMovement
    {
        return $this->createMovement($data + [
            'movement_type' => MaterialStockMovementType::OPENING,
            'direction' => StockMovementDirection::IN,
        ]);
    }

    public function createAdjustment(array $data): MaterialStockMovement
    {
        $direction = StockMovementDirection::from($data['direction']);

        if ($direction === StockMovementDirection::OUT) {
            $material = Material::query()->lockForUpdate()->findOrFail($data['material_id']);
            $availableStock = $this->getCurrentStock($material);

            if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "الرصيد غير كافٍ. المتاح: {$availableStock}. المطلوب: {$data['quantity']}.",
                ]);
            }
        }

        return $this->createMovement($data + [
            'movement_type' => $direction === StockMovementDirection::IN
                ? MaterialStockMovementType::ADJUSTMENT_IN
                : MaterialStockMovementType::ADJUSTMENT_OUT,
        ]);
    }

    public function createPurchaseReceiptMovement(array $data): MaterialStockMovement
    {
        $this->assertReferenceNotPosted(
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['material_id'],
        );

        return $this->createMovement($data + [
            'movement_type' => MaterialStockMovementType::PURCHASE_RECEIPT,
            'direction' => StockMovementDirection::IN,
        ]);
    }

    public function createPurchaseReceiptMovements(GoodsReceipt $receipt): void
    {
        if (MaterialStockMovement::query()
            ->where('reference_type', GoodsReceipt::class)
            ->where('reference_id', $receipt->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => 'تم ترحيل إذن الاستلام إلى المخزون بالفعل.',
            ]);
        }

        foreach ($receipt->items as $item) {
            if (bccomp((string) $item->accepted_quantity, '0', 3) <= 0) {
                continue;
            }

            $this->createPurchaseReceiptMovement([
                'material_id' => $item->material_id,
                'quantity' => $item->accepted_quantity,
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'movement_date' => $receipt->received_at->toDateString(),
                'notes' => 'إذن استلام',
                'user_id' => $receipt->received_by,
            ]);
        }
    }

    public function createPurchaseReturnMovements(PurchaseReturn $return): void
    {
        if (MaterialStockMovement::query()
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => 'تم ترحيل المرتجع إلى المخزون بالفعل.',
            ]);
        }

        foreach ($return->items as $item) {
            $this->createPurchaseReturnMovement([
                'material_id' => $item->material_id,
                'quantity' => $item->quantity,
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $return->id,
                'reference_number' => $return->return_number,
                'movement_date' => $return->return_date->toDateString(),
                'notes' => 'مرتجع مشتريات',
                'user_id' => $return->created_by,
            ]);
        }
    }

    public function createPurchaseReturnMovement(array $data): MaterialStockMovement
    {
        $this->assertReferenceNotPosted(
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['material_id'],
        );

        $material = Material::query()->lockForUpdate()->findOrFail($data['material_id']);
        $availableStock = $this->getCurrentStock($material);

        if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
            throw ValidationException::withMessages([
                'quantity' => "الرصيد غير كافٍ. المتاح: {$availableStock}. المطلوب: {$data['quantity']}.",
            ]);
        }

        return $this->createMovement($data + [
            'movement_type' => MaterialStockMovementType::PURCHASE_RETURN,
            'direction' => StockMovementDirection::OUT,
        ]);
    }

    public function referenceAlreadyPosted(?string $referenceType, ?int $referenceId, int $materialId): bool
    {
        if (blank($referenceType) || blank($referenceId)) {
            return false;
        }

        return MaterialStockMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('material_id', $materialId)
            ->exists();
    }

    private function assertReferenceNotPosted(?string $referenceType, ?int $referenceId, int $materialId): void
    {
        if ($this->referenceAlreadyPosted($referenceType, $referenceId, $materialId)) {
            throw ValidationException::withMessages([
                'reference' => 'تم ترحيل حركة مخزون لهذا المرجع وهذه الخامة بالفعل.',
            ]);
        }
    }

    private function createMovement(array $data): MaterialStockMovement
    {
        $material = Material::query()
            ->lockForUpdate()
            ->findOrFail($data['material_id']);

        $direction = $data['direction'] instanceof StockMovementDirection
            ? $data['direction']
            : StockMovementDirection::from($data['direction']);

        if ($direction === StockMovementDirection::OUT) {
            $availableStock = $this->getCurrentStock($material);

            if (bccomp($availableStock, (string) $data['quantity'], 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة أكبر من الرصيد المتاح. المتاح: {$availableStock}. المطلوب: {$data['quantity']}.",
                ]);
            }
        }

        $movement = MaterialStockMovement::create($data);
        $this->syncLegacyQuantity($material);

        return $movement;
    }

    private function syncLegacyQuantity(Material $material): void
    {
        $material->update(['quantity' => $this->getCurrentStock($material)]);
    }
}
