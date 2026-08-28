<?php

namespace Database\Seeders;

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\RecordSupplierPaymentAction;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoProcurementSeeder extends Seeder
{
    public function run(): void
    {
        Auth::login(User::query()->where('email', 'demo.dataset@abaya.test')->firstOrFail());

        $definitions = [
            ['001', 'DEMO-SUP-002', '2026-01-02', [['DEMO-MAT-NIDA-BLK', 100, 10]]],
            ['002', 'DEMO-SUP-002', '2026-01-05', [['DEMO-MAT-NIDA-BLK', 100, 12]]],
            ['003', 'DEMO-SUP-002', '2026-01-08', [['DEMO-MAT-NIDA-BLK', 200, 14]]],
            ['004', 'DEMO-SUP-001', '2026-01-10', [['DEMO-MAT-NIDA-BGE', 500, 18]]],
            ['005', 'DEMO-SUP-001', '2026-01-12', [['DEMO-MAT-CREPE-NVY', 500, 22]]],
            ['006', 'DEMO-SUP-003', '2026-01-14', [['DEMO-MAT-THREAD-BLK', 100, 30]]],
            ['007', 'DEMO-SUP-003', '2026-01-16', [['DEMO-MAT-ZIP-60', 500, 4], ['DEMO-MAT-BUTTON', 2000, 1]]],
            ['008', 'DEMO-SUP-004', '2026-01-18', [['DEMO-MAT-LINING', 500, 9], ['DEMO-MAT-PACK', 500, 2]]],
            ['009', 'DEMO-SUP-002', '2026-01-20', [['DEMO-FIFO32-NIDA-BLK', 100, 10]]],
            ['010', 'DEMO-SUP-002', '2026-01-21', [['DEMO-FIFO32-NIDA-BLK', 100, 12]]],
            ['011', 'DEMO-SUP-002', '2026-01-22', [['DEMO-FIFO150-NIDA-BLK', 100, 10]]],
            ['012', 'DEMO-SUP-002', '2026-01-23', [['DEMO-FIFO150-NIDA-BLK', 100, 12]]],
        ];

        $receipts = collect();
        foreach ($definitions as [$suffix, $supplierCode, $date, $lines]) {
            $supplier = Supplier::query()->where('code', $supplierCode)->firstOrFail();
            $subtotal = collect($lines)->sum(fn ($line) => $line[1] * $line[2]);
            $order = PurchaseOrder::query()->firstOrCreate(
                ['purchase_order_number' => 'DEMO-PO-'.$suffix],
                ['supplier_id' => $supplier->id, 'order_date' => $date, 'expected_delivery_date' => $date, 'status' => 'approved', 'notes' => 'DEMO-DATASET', 'subtotal' => $subtotal, 'discount' => 0, 'tax' => 0, 'total' => $subtotal],
            );
            foreach ($lines as [$materialCode, $quantity, $unitCost]) {
                $material = Material::query()->where('code', $materialCode)->firstOrFail();
                $order->items()->firstOrCreate(['material_id' => $material->id], ['quantity' => $quantity, 'unit_price' => $unitCost, 'discount' => 0, 'tax' => 0, 'line_total' => $quantity * $unitCost]);
            }

            $receipt = GoodsReceipt::query()->firstOrCreate(
                ['receipt_number' => 'DEMO-GR-'.$suffix],
                ['purchase_order_id' => $order->id, 'supplier_id' => $supplier->id, 'received_at' => $date.' 10:00:00', 'received_by' => auth()->id(), 'status' => 'draft', 'notes' => 'DEMO-DATASET'],
            );
            foreach ($order->items as $item) {
                $receipt->items()->firstOrCreate(['purchase_order_item_id' => $item->id], ['material_id' => $item->material_id, 'ordered_quantity' => $item->quantity, 'received_quantity' => $item->quantity, 'accepted_quantity' => $item->quantity, 'rejected_quantity' => 0, 'unit_cost' => $item->unit_price, 'notes' => 'استلام كامل مطابق للمواصفات']);
            }
            if ($receipt->status === 'draft') {
                app(ConfirmGoodsReceiptAction::class)->handle($receipt);
            }
            $receipts[$suffix] = $receipt->fresh();

            SupplierInvoice::query()->firstOrCreate(
                ['invoice_number' => 'DEMO-SINV-'.$suffix],
                ['supplier_id' => $supplier->id, 'purchase_order_id' => $order->id, 'goods_receipt_id' => $receipt->id, 'invoice_date' => $date, 'due_date' => '2026-03-01', 'subtotal' => $subtotal, 'discount' => 0, 'tax' => 0, 'total' => $subtotal, 'paid_amount' => 0, 'remaining_amount' => $subtotal, 'status' => 'unpaid', 'notes' => 'DEMO-DATASET'],
            );
        }

        foreach ([['001', 1000], ['002', 600], ['004', 9000], ['005', 5000], ['007', 4000]] as [$suffix, $amount]) {
            $invoice = SupplierInvoice::query()->where('invoice_number', 'DEMO-SINV-'.$suffix)->firstOrFail();
            if (! SupplierPayment::query()->where('reference', 'DEMO-BANK-'.$suffix)->exists()) {
                app(RecordSupplierPaymentAction::class)->handle(['supplier_invoice_id' => $invoice->id, 'purchase_order_id' => $invoice->purchase_order_id, 'payment_type' => 'invoice', 'payment_date' => '2026-02-01', 'amount' => $amount, 'payment_method' => 'bank_transfer', 'reference' => 'DEMO-BANK-'.$suffix, 'notes' => 'DEMO-DATASET']);
            }
        }

        $receipt = $receipts['008'];
        $return = PurchaseReturn::query()->firstOrCreate(
            ['return_number' => 'DEMO-PR-001'],
            ['supplier_id' => $receipt->supplier_id, 'purchase_order_id' => $receipt->purchase_order_id, 'goods_receipt_id' => $receipt->id, 'return_date' => '2026-01-22', 'reason' => 'عيب في جزء من البطانة', 'status' => 'draft', 'notes' => 'DEMO-DATASET', 'created_by' => auth()->id()],
        );
        $lining = Material::query()->where('code', 'DEMO-MAT-LINING')->firstOrFail();
        $return->items()->firstOrCreate(['material_id' => $lining->id], ['quantity' => 20, 'unit_cost' => 9, 'reason' => 'اختلاف درجة اللون']);
        if ($return->status === 'draft') {
            app(ConfirmPurchaseReturnAction::class)->handle($return);
        }
    }
}
