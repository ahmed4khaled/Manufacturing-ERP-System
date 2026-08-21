<?php

namespace App\Actions\ProductionOrder;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Services\ProductionActivityService;
use App\Services\ProductionSummaryService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordProductionOutputAction
{
    public function __construct(private readonly ProductionSummaryService $summary, private readonly StockService $stock, private readonly ProductionActivityService $activity) {}

    public function handle(ProductionOrder $order, array $data): ProductionOutput
    {
        return DB::transaction(function () use ($order, $data) {
            $order = ProductionOrder::query()->with(['workAssignments', 'operations'])->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, [ProductionOrderStatus::IN_PROGRESS, ProductionOrderStatus::PARTIALLY_COMPLETED], true)) {
                throw ValidationException::withMessages(['status' => 'حالة أمر الإنتاج لا تسمح بترحيل إنتاج تام.']);
            }
            $summary = $this->summary->orderSummary($order);
            $newTotal = bcadd($summary['output'], (string) $data['quantity'], 3);
            if (bccomp($newTotal, $summary['good'], 3) > 0 || bccomp($newTotal, (string) $order->planned_quantity, 3) > 0) {
                throw ValidationException::withMessages(['quantity' => 'الإنتاج المرحل يتجاوز الإنتاج الجيد المتاح.']);
            }
            $output = ProductionOutput::create(['production_order_id' => $order->id, 'product_variant_id' => $order->product_variant_id, 'quantity' => $data['quantity'], 'output_date' => $data['output_date'], 'notes' => $data['notes'] ?? null, 'recorded_by' => auth()->id()]);
            $output->load('productionOrder');
            $this->stock->createProductionOutputMovement($output);
            $output->update(['posted_at' => now()]);
            $order->update(['status' => ProductionOrderStatus::PARTIALLY_COMPLETED]);
            $this->activity->record($order, 'output_posted', 'تم ترحيل إنتاج تام إلى مخزون المنتجات.', ['output_id' => $output->id, 'quantity' => $output->quantity]);

            return $output;
        });
    }
}
