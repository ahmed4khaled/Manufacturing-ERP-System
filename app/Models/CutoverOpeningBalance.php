<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CutoverOpeningBalance extends Model
{
    public const TYPES = ['cash_bank', 'material', 'finished_goods', 'wip', 'worker_payable', 'partner', 'other'];

    protected $fillable = [
        'cutover_batch_id', 'type', 'account_id', 'material_id', 'product_variant_id',
        'production_order_id', 'worker_id', 'partner_id', 'partner_balance_type',
        'quantity', 'unit_cost', 'amount', 'layer_reference', 'description',
        'material_stock_movement_id', 'stock_movement_id', 'material_cost_layer_id',
        'product_cost_layer_id', 'production_worker_wage_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            if ($line->batch()->where('status', '!=', CutoverBatch::STATUS_DRAFT)->exists()) {
                throw ValidationException::withMessages(['cutover' => 'لا يمكن تعديل تفاصيل دفعة Cutover بعد ترحيلها.']);
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CutoverBatch::class, 'cutover_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class)->withTrashed();
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function materialStockMovement(): BelongsTo
    {
        return $this->belongsTo(MaterialStockMovement::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function materialCostLayer(): BelongsTo
    {
        return $this->belongsTo(MaterialCostLayer::class);
    }

    public function productCostLayer(): BelongsTo
    {
        return $this->belongsTo(ProductCostLayer::class);
    }

    public function productionWorkerWage(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkerWage::class);
    }
}
