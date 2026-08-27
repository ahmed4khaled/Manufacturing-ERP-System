<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->where('email', 'sales@abaya.test')->value('id');
        $customers = collect([
            ['code' => 'CUS-001', 'name' => 'بوتيك الياسمين', 'phone' => '01030000001', 'city' => 'القاهرة', 'address' => 'مدينة نصر', 'credit_limit' => 50000],
            ['code' => 'CUS-002', 'name' => 'معرض لمسة', 'phone' => '01030000002', 'city' => 'الإسكندرية', 'address' => 'سموحة', 'credit_limit' => 35000],
            ['code' => 'CUS-003', 'name' => 'دار الأناقة', 'phone' => '01030000003', 'city' => 'المنصورة', 'address' => 'شارع الجمهورية', 'credit_limit' => 25000],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => Customer::query()->updateOrCreate(['code' => $data['code']], $data + ['status' => 'active', 'notes' => 'عميل تجريبي']),
        ]);

        $customer = $customers['CUS-001'];
        $variantOne = ProductVariant::query()->with(['product', 'color', 'size'])->where('sku', 'ABY-001-BLK-M')->firstOrFail();
        $variantTwo = ProductVariant::query()->with(['product', 'color', 'size'])->where('sku', 'ABY-002-BLK-L')->firstOrFail();
        $invoice = SalesInvoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-DEMO-001'],
            ['customer_id' => $customer->id, 'invoice_date' => now()->subDays(7), 'notes' => 'فاتورة مبيعات مؤكدة للتجربة', 'status' => 'confirmed', 'total_quantity' => 5, 'subtotal' => 5650, 'total_amount' => 5650],
        );

        foreach ([[$variantOne, 3, 950], [$variantTwo, 2, 1400]] as [$variant, $quantity, $price]) {
            $item = $invoice->items()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                ['product_name' => $variant->product->name, 'color_name' => $variant->color->name, 'size_name' => $variant->size->name, 'sku' => $variant->sku, 'barcode' => $variant->barcode, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $quantity * $price],
            );
            StockMovement::query()->updateOrCreate(
                ['reference_type' => SalesInvoice::class, 'reference_id' => $invoice->id, 'product_variant_id' => $variant->id],
                ['movement_type' => 'sale', 'direction' => 'out', 'quantity' => $quantity, 'reference_number' => $invoice->invoice_number, 'movement_date' => $invoice->invoice_date, 'notes' => 'صرف فاتورة مبيعات تجريبية', 'user_id' => $userId],
            );
        }

        CustomerLedgerEntry::query()->updateOrCreate(
            ['reference_type' => 'sales_invoice', 'reference_id' => $invoice->id],
            ['customer_id' => $customer->id, 'reference_number' => $invoice->invoice_number, 'transaction_date' => $invoice->invoice_date, 'description' => 'فاتورة مبيعات', 'debit' => 5650, 'credit' => 0, 'notes' => 'قيد تلقائي تجريبي', 'created_by' => $userId],
        );

        $payment = PaymentReceipt::query()->updateOrCreate(
            ['receipt_number' => 'RCPT-DEMO-001'],
            ['customer_id' => $customer->id, 'sales_invoice_id' => $invoice->id, 'payment_type' => 'invoice', 'payment_date' => now()->subDays(5), 'amount' => 3000, 'payment_method' => 'cash', 'reference_number' => 'CASH-DEMO-001', 'notes' => 'دفعة جزئية من العميل', 'status' => 'confirmed', 'created_by' => $userId],
        );
        CustomerLedgerEntry::query()->updateOrCreate(
            ['reference_type' => 'payment_receipt', 'reference_id' => $payment->id],
            ['customer_id' => $customer->id, 'reference_number' => $payment->receipt_number, 'transaction_date' => $payment->payment_date, 'description' => 'سند قبض', 'debit' => 0, 'credit' => 3000, 'created_by' => $userId],
        );

        $invoiceItem = $invoice->items()->where('product_variant_id', $variantOne->id)->firstOrFail();
        $return = SalesReturn::query()->updateOrCreate(
            ['return_number' => 'SRET-DEMO-001'],
            ['sales_invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'return_date' => now()->subDays(3), 'status' => 'confirmed', 'reason' => 'تغيير المقاس', 'notes' => 'مرتجع مبيعات تجريبي', 'total_quantity' => 1, 'total_amount' => 950, 'created_by' => $userId],
        );
        $return->items()->updateOrCreate(
            ['sales_invoice_item_id' => $invoiceItem->id],
            ['product_variant_id' => $variantOne->id, 'product_name' => $variantOne->product->name, 'color_name' => $variantOne->color->name, 'size_name' => $variantOne->size->name, 'sku' => $variantOne->sku, 'quantity' => 1, 'unit_price' => 950, 'line_total' => 950],
        );
        StockMovement::query()->updateOrCreate(
            ['reference_type' => SalesReturn::class, 'reference_id' => $return->id, 'product_variant_id' => $variantOne->id],
            ['movement_type' => 'sales_return', 'direction' => 'in', 'quantity' => 1, 'reference_number' => $return->return_number, 'movement_date' => $return->return_date, 'notes' => 'إضافة مرتجع العميل للمخزون', 'user_id' => $userId],
        );
        CustomerLedgerEntry::query()->updateOrCreate(
            ['reference_type' => 'sales_return', 'reference_id' => $return->id],
            ['customer_id' => $customer->id, 'reference_number' => $return->return_number, 'transaction_date' => $return->return_date, 'description' => 'مرتجع مبيعات', 'debit' => 0, 'credit' => 950, 'created_by' => $userId],
        );
    }
}
