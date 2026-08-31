<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialCostAllocation;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\Product;
use App\Models\ProductionMaterialTransaction;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\ProductionWorkerWage;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Worker;
use App\Models\WorkerWagePayment;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoMasterDataSeeder::class,
            DemoProcurementSeeder::class,
            DemoManufacturingSeeder::class,
            WorkerWageDemoSeeder::class,
            DemoSalesSeeder::class,
        ]);

        $this->verifyDataset();
    }

    private function verifyDataset(): void
    {
        $counts = [
            'customers' => Customer::query()->where('code', 'like', 'DEMO-CUS-%')->count(),
            'suppliers' => Supplier::query()->where('code', 'like', 'DEMO-SUP-%')->count(),
            'materials' => Material::query()->where(fn ($query) => $query->where('code', 'like', 'DEMO-MAT-%')->orWhere('code', 'like', 'DEMO-FIFO%'))->count(),
            'products' => Product::query()->where('code', 'like', 'DEMO-%')->count(),
            'variants' => ProductVariant::query()->where('sku', 'like', 'DEMO-%')->count(),
            'workers' => Worker::query()->where('code', 'like', 'DEMO-WRK-%')->count(),
            'boms' => Bom::query()->where('bom_number', 'like', 'DEMO-BOM-%')->count(),
            'purchase_orders' => PurchaseOrder::query()->where('purchase_order_number', 'like', 'DEMO-PO-%')->count(),
            'goods_receipts' => GoodsReceipt::query()->where('receipt_number', 'like', 'DEMO-GR-%')->count(),
            'production_orders' => ProductionOrder::query()->where('notes', 'like', 'DEMO-DATASET-PRODUCTION-%')->count(),
            'production_outputs' => ProductionOutput::query()->whereIn('production_order_id', ProductionOrder::query()->where('notes', 'like', 'DEMO-DATASET-PRODUCTION-%')->pluck('id'))->count(),
            'worker_wages' => ProductionWorkerWage::query()->whereIn('production_order_id', ProductionOrder::query()->where('notes', 'like', 'DEMO-DATASET-PRODUCTION-%')->pluck('id'))->count(),
            'worker_wage_payments' => WorkerWagePayment::query()->where('reference', 'like', 'DEMO-WAGE-PAY-%')->count(),
            'sales_invoices' => SalesInvoice::query()->where('notes', 'like', 'DEMO-DATASET-SALES-%')->count(),
            'payment_receipts' => PaymentReceipt::query()->where('notes', 'like', 'DEMO-DATASET-PAYMENT-%')->count(),
            'receipt_allocations' => PaymentReceiptAllocation::query()->whereIn('payment_receipt_id', PaymentReceipt::query()->where('notes', 'like', 'DEMO-DATASET-PAYMENT-%')->pluck('id'))->count(),
            'supplier_invoices' => SupplierInvoice::query()->where('invoice_number', 'like', 'DEMO-SINV-%')->count(),
            'supplier_payments' => SupplierPayment::query()->where('reference', 'like', 'DEMO-BANK-%')->count(),
            'sales_returns' => SalesReturn::query()->where('notes', 'DEMO-DATASET-SALES-RETURN-01')->count(),
            'purchase_returns' => PurchaseReturn::query()->where('return_number', 'DEMO-PR-001')->count(),
        ];
        $expected = ['customers' => 5, 'suppliers' => 5, 'materials' => 10, 'products' => 4, 'variants' => 18, 'workers' => 8, 'boms' => 8, 'purchase_orders' => 12, 'goods_receipts' => 12, 'production_orders' => 8, 'production_outputs' => 8, 'worker_wages' => 26, 'worker_wage_payments' => 3, 'sales_invoices' => 10, 'payment_receipts' => 5, 'receipt_allocations' => 5, 'supplier_invoices' => 12, 'supplier_payments' => 5, 'sales_returns' => 1, 'purchase_returns' => 1];
        if ($counts !== $expected) {
            throw new RuntimeException('Demo dataset count verification failed: '.json_encode($counts));
        }

        $order = ProductionOrder::query()->where('notes', 'DEMO-DATASET-PRODUCTION-001')->firstOrFail();
        $allocations = MaterialCostAllocation::query()
            ->where('reference_type', ProductionMaterialTransaction::class)
            ->whereIn('reference_id', $order->materialTransactions()->pluck('id'))
            ->where('direction', 'out')->orderBy('id')->get();
        if ($allocations->count() !== 2
            || (string) $allocations[0]->quantity !== '100.000'
            || (string) $allocations[0]->unit_cost !== '10.0000'
            || (string) $allocations[1]->quantity !== '50.000'
            || (string) $allocations[1]->unit_cost !== '12.0000'
            || (string) $order->actual_material_cost !== '1600.00') {
            throw new RuntimeException('Demo FIFO verification failed.');
        }

        $multiReceipt = PaymentReceipt::query()->where('notes', 'DEMO-DATASET-PAYMENT-01')->firstOrFail();
        if ($multiReceipt->allocations()->count() !== 2 || (string) $multiReceipt->allocations()->sum('amount') !== '15000.00') {
            throw new RuntimeException('Demo multi-invoice allocation verification failed.');
        }

        $this->command?->info('Verified demo records: '.json_encode($counts));
        $this->command?->info('Verified FIFO: 100 x 10 + 50 x 12 = 1,600 EGP.');

        $this->verifyIsolatedFifoScenario('FIFO32', 'DEMO-FIFO32-NIDA-BLK', '10.000', '32.000', '320.00', [['32.000', '10.0000', '320.00']], [['68.000', '10.0000'], ['100.000', '12.0000']]);
        $this->verifyIsolatedFifoScenario('FIFO150', 'DEMO-FIFO150-NIDA-BLK', '150.000', '150.000', '1600.00', [['100.000', '10.0000', '1000.00'], ['50.000', '12.0000', '600.00']], [['50.000', '12.0000']]);
    }

    private function verifyIsolatedFifoScenario(string $key, string $materialCode, string $planned, string $issued, string $actualCost, array $expectedAllocations, array $expectedAvailableLayers): void
    {
        $material = Material::query()->where('code', $materialCode)->firstOrFail();
        $layers = $material->costLayers()->with('goodsReceipt')->orderBy('layer_date')->orderBy('id')->get();
        $availableLayers = $layers->where('remaining_quantity', '>', 0)->values();
        $order = ProductionOrder::query()->where('notes', 'DEMO-DATASET-PRODUCTION-'.$key)->firstOrFail();
        $transactions = $order->materialTransactions;
        $allocations = MaterialCostAllocation::query()->where('reference_type', ProductionMaterialTransaction::class)->whereIn('reference_id', $transactions->pluck('id'))->where('direction', 'out')->orderBy('id')->get();
        $assignment = $order->workAssignments()->firstOrFail();

        $actualAllocations = $allocations->map(fn ($allocation) => [(string) $allocation->quantity, (string) $allocation->unit_cost, (string) $allocation->total_cost])->all();
        $actualAvailableLayers = $availableLayers->map(fn ($layer) => [(string) $layer->remaining_quantity, (string) $layer->unit_cost])->all();
        $consistent = bccomp((string) $order->planned_quantity, $planned, 3) === 0
            && bccomp((string) $order->workAssignments()->sum('assigned_quantity'), $planned, 3) === 0
            && bccomp((string) $transactions->filter(fn ($transaction) => $transaction->type->value === 'issue')->sum('quantity'), $issued, 3) === 0
            && bccomp((string) $transactions->filter(fn ($transaction) => $transaction->type->value === 'usage')->sum('quantity'), $issued, 3) === 0
            && bccomp((string) $transactions->filter(fn ($transaction) => $transaction->type->value === 'return')->sum('quantity'), '0', 3) === 0
            && bccomp((string) $transactions->filter(fn ($transaction) => $transaction->type->value === 'waste')->sum('quantity'), '0', 3) === 0
            && bccomp((string) $assignment->produced_quantity, $planned, 3) === 0
            && bccomp((string) $assignment->good_quantity, $planned, 3) === 0
            && bccomp((string) $assignment->defective_quantity, '0', 3) === 0
            && bccomp((string) $order->outputs()->sum('quantity'), $planned, 3) === 0;

        if ($layers->count() !== 2 || $layers->whereNull('unit_cost')->isNotEmpty() || $layers->whereNull('goods_receipt_id')->isNotEmpty()
            || $layers->contains(fn ($layer) => $layer->goodsReceipt?->status !== 'confirmed')
            || $actualAllocations !== $expectedAllocations || $actualAvailableLayers !== $expectedAvailableLayers
            || (string) $order->actual_material_cost !== $actualCost || ! $consistent) {
            throw new RuntimeException('Isolated demo FIFO verification failed for '.$key.': '.json_encode([
                'layer_count' => $layers->count(),
                'allocations' => $actualAllocations,
                'available_layers' => $actualAvailableLayers,
                'actual_material_cost' => (string) $order->actual_material_cost,
                'planned' => (string) $order->planned_quantity,
                'assigned' => (string) $order->workAssignments()->sum('assigned_quantity'),
                'transactions' => $transactions->map(fn ($transaction) => [$transaction->type->value, (string) $transaction->quantity])->all(),
                'produced' => (string) $assignment->produced_quantity,
                'good' => (string) $assignment->good_quantity,
                'defective' => (string) $assignment->defective_quantity,
                'output' => (string) $order->outputs()->sum('quantity'),
                'consistent' => $consistent,
            ]));
        }

        $layerSummary = $layers->map(fn ($layer) => "#{$layer->id} {$layer->goodsReceipt->receipt_number}: {$layer->original_quantity} @ {$layer->unit_cost}, remaining {$layer->remaining_quantity}")->implode('; ');
        $this->command?->info("Verified {$key} {$order->order_number}: planned/assigned/good/output {$planned}; issued/used {$issued}; actual material cost {$actualCost} EGP.");
        $this->command?->info('Valued layers: '.$layerSummary);
    }
}
