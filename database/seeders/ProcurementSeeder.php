<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->where('email', 'purchasing@abaya.test')->value('id');
        $fabric = Material::query()->where('code', 'MAT-FAB-001')->firstOrFail();
        $thread = Material::query()->where('code', 'MAT-THR-001')->firstOrFail();

        $supplier = Supplier::query()->updateOrCreate(
            ['code' => 'SUP-001'],
            ['name' => 'شركة النسيج الحديثة', 'phone' => '01010000001', 'email' => 'sales@modern-textile.test', 'address' => 'مدينة العبور، القاهرة', 'tax_number' => 'TAX-100001', 'notes' => 'مورد الأقمشة الرئيسي', 'status' => 'active'],
        );
        Supplier::query()->updateOrCreate(
            ['code' => 'SUP-002'],
            ['name' => 'مستلزمات الخياطة المتحدة', 'phone' => '01010000002', 'email' => 'orders@sewing-supplies.test', 'address' => 'المنطقة الصناعية، الجيزة', 'tax_number' => 'TAX-100002', 'status' => 'active'],
        );

        DB::table('supplier_contacts')->updateOrInsert(
            ['supplier_id' => $supplier->id, 'email' => 'ahmed@modern-textile.test'],
            ['name' => 'أحمد محمود', 'job_title' => 'مسؤول المبيعات', 'phone' => '01020000001', 'notes' => 'التواصل خلال ساعات العمل', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        $order = PurchaseOrder::query()->updateOrCreate(
            ['purchase_order_number' => 'PO-DEMO-001'],
            ['supplier_id' => $supplier->id, 'order_date' => now()->subDays(20), 'expected_delivery_date' => now()->subDays(14), 'status' => 'approved', 'notes' => 'طلب شراء تجريبي معتمد', 'subtotal' => 20500, 'discount' => 500, 'tax' => 2800, 'total' => 22800],
        );
        $fabricItem = $order->items()->updateOrCreate(
            ['material_id' => $fabric->id],
            ['quantity' => 150, 'unit_price' => 120, 'discount' => 500, 'tax' => 2450, 'line_total' => 19950],
        );
        $threadItem = $order->items()->updateOrCreate(
            ['material_id' => $thread->id],
            ['quantity' => 50, 'unit_price' => 50, 'discount' => 0, 'tax' => 350, 'line_total' => 2850],
        );

        $receipt = GoodsReceipt::query()->updateOrCreate(
            ['receipt_number' => 'GR-DEMO-001'],
            ['purchase_order_id' => $order->id, 'supplier_id' => $supplier->id, 'received_at' => now()->subDays(14), 'received_by' => $userId, 'status' => 'confirmed', 'notes' => 'تم الفحص والاستلام'],
        );
        $receipt->items()->updateOrCreate(
            ['purchase_order_item_id' => $fabricItem->id],
            ['material_id' => $fabric->id, 'ordered_quantity' => 150, 'received_quantity' => 150, 'accepted_quantity' => 148, 'rejected_quantity' => 2, 'unit_cost' => 120, 'notes' => 'تم رفض مترين لعيب في النسيج'],
        );
        $receipt->items()->updateOrCreate(
            ['purchase_order_item_id' => $threadItem->id],
            ['material_id' => $thread->id, 'ordered_quantity' => 50, 'received_quantity' => 50, 'accepted_quantity' => 50, 'rejected_quantity' => 0, 'unit_cost' => 50],
        );

        foreach ([[$fabric, 148], [$thread, 50]] as [$material, $quantity]) {
            MaterialStockMovement::query()->updateOrCreate(
                ['reference_type' => GoodsReceipt::class, 'reference_id' => $receipt->id],
                ['material_id' => $material->id, 'movement_type' => 'purchase_receipt', 'direction' => 'in', 'quantity' => $quantity, 'reference_number' => $receipt->receipt_number, 'movement_date' => now()->subDays(14)->toDateString(), 'notes' => 'استلام مشتريات تجريبي', 'user_id' => $userId],
            );
        }

        $invoice = SupplierInvoice::query()->updateOrCreate(
            ['invoice_number' => 'SINV-DEMO-001'],
            ['supplier_id' => $supplier->id, 'purchase_order_id' => $order->id, 'goods_receipt_id' => $receipt->id, 'invoice_date' => now()->subDays(13), 'due_date' => now()->addDays(17), 'subtotal' => 20500, 'discount' => 500, 'tax' => 2800, 'total' => 22800, 'paid_amount' => 10000, 'remaining_amount' => 12800, 'status' => 'partially_paid', 'notes' => 'فاتورة مورد تجريبية'],
        );
        SupplierPayment::query()->updateOrCreate(
            ['supplier_id' => $supplier->id, 'reference' => 'BANK-DEMO-001'],
            ['purchase_order_id' => $order->id, 'supplier_invoice_id' => $invoice->id, 'payment_type' => 'invoice', 'payment_date' => now()->subDays(8), 'amount' => 10000, 'payment_method' => 'bank_transfer', 'notes' => 'دفعة أولى للمورد'],
        );

        $return = PurchaseReturn::query()->updateOrCreate(
            ['return_number' => 'PR-DEMO-001'],
            ['supplier_id' => $supplier->id, 'purchase_order_id' => $order->id, 'goods_receipt_id' => $receipt->id, 'return_date' => now()->subDays(10), 'reason' => 'عيب جودة في جزء من القماش', 'status' => 'confirmed', 'notes' => 'مرتجع مشتريات تجريبي', 'created_by' => $userId],
        );
        $return->items()->updateOrCreate(
            ['material_id' => $fabric->id],
            ['quantity' => 10, 'unit_cost' => 120, 'reason' => 'اختلاف درجة اللون'],
        );
        MaterialStockMovement::query()->updateOrCreate(
            ['reference_type' => PurchaseReturn::class, 'reference_id' => $return->id],
            ['material_id' => $fabric->id, 'movement_type' => 'purchase_return', 'direction' => 'out', 'quantity' => 10, 'reference_number' => $return->return_number, 'movement_date' => now()->subDays(10)->toDateString(), 'notes' => 'مرتجع إلى المورد', 'user_id' => $userId],
        );
    }
}
