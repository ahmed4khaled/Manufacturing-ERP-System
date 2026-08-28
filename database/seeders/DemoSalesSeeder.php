<?php

namespace Database\Seeders;

use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\SalesInvoice\ConfirmSalesInvoiceAction;
use App\Actions\SalesInvoice\CreateSalesInvoiceAction;
use App\Actions\SalesReturn\ConfirmSalesReturnAction;
use App\Actions\SalesReturn\CreateSalesReturnAction;
use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        Auth::login(User::query()->where('email', 'demo.dataset@abaya.test')->firstOrFail());

        $definitions = [
            ['01', 'DEMO-CUS-001', 'DEMO-3037-DEMO-BLK-M', 20],
            ['02', 'DEMO-CUS-001', 'DEMO-3037-DEMO-BLK-M', 10],
            ['03', 'DEMO-CUS-002', 'DEMO-3037-DEMO-BLK-L', 8],
            ['04', 'DEMO-CUS-002', 'DEMO-3037-DEMO-BGE-M', 6],
            ['05', 'DEMO-CUS-003', 'DEMO-4102-DEMO-BLK-M', 5],
            ['06', 'DEMO-CUS-003', 'DEMO-5201-DEMO-OLV-M', 6],
            ['07', 'DEMO-CUS-004', 'DEMO-6105-DEMO-NVY-M', 4],
            ['08', 'DEMO-CUS-001', 'DEMO-3037-DEMO-BLK-L', 5],
            ['09', 'DEMO-CUS-005', 'DEMO-3037-DEMO-BGE-M', 4],
            ['10', 'DEMO-CUS-001', 'DEMO-4102-DEMO-BLK-M', 3],
        ];

        $invoices = collect();
        foreach ($definitions as [$suffix, $customerCode, $sku, $quantity]) {
            $marker = 'DEMO-DATASET-SALES-'.$suffix;
            $invoice = SalesInvoice::query()->where('notes', $marker)->first();
            if (! $invoice) {
                $customer = Customer::query()->where('code', $customerCode)->firstOrFail();
                $variant = ProductVariant::query()->where('sku', $sku)->firstOrFail();
                $invoice = app(CreateSalesInvoiceAction::class)->handle(['customer_id' => $customer->id, 'invoice_date' => '2026-03-'.str_pad($suffix, 2, '0', STR_PAD_LEFT), 'discount' => 0, 'notes' => $marker, 'products' => [['product_id' => $variant->product_id, 'variants' => [['product_variant_id' => $variant->id, 'quantity' => $quantity]]]]]);
                app(ConfirmSalesInvoiceAction::class)->handle($invoice);
            }
            $invoices[$suffix] = $invoice->fresh('items');
        }

        $this->createReceipt('01', 'DEMO-CUS-001', 15000, [['01', 10000], ['02', 5000]], $invoices);
        $this->createReceipt('02', 'DEMO-CUS-002', 4000, [['03', 4000]], $invoices);
        $this->createReceipt('03', 'DEMO-CUS-002', 1500, [['04', 1500]], $invoices);
        $this->createReceipt('04', 'DEMO-CUS-003', 3750, [['05', 3750]], $invoices);

        if (! PaymentReceipt::query()->where('notes', 'DEMO-DATASET-PAYMENT-05')->exists()) {
            $customer = Customer::query()->where('code', 'DEMO-CUS-003')->firstOrFail();
            $receipt = app(CreatePaymentReceiptAction::class)->handle(['customer_id' => $customer->id, 'payment_type' => 'advance', 'payment_date' => '2026-03-20', 'amount' => 2000, 'payment_method' => 'cash', 'reference_number' => 'DEMO-CASH-05', 'notes' => 'DEMO-DATASET-PAYMENT-05', 'allocations' => []]);
            app(ConfirmPaymentReceiptAction::class)->handle($receipt);
        }

        if (! SalesReturn::query()->where('notes', 'DEMO-DATASET-SALES-RETURN-01')->exists()) {
            $invoice = $invoices['06'];
            $return = app(CreateSalesReturnAction::class)->handle(['sales_invoice_id' => $invoice->id, 'return_date' => '2026-03-22', 'reason' => 'تغيير المقاس', 'notes' => 'DEMO-DATASET-SALES-RETURN-01', 'items' => [['sales_invoice_item_id' => $invoice->items->first()->id, 'quantity' => 1]]]);
            app(ConfirmSalesReturnAction::class)->handle($return);
        }
    }

    private function createReceipt(string $suffix, string $customerCode, int $amount, array $lines, $invoices): void
    {
        if (PaymentReceipt::query()->where('notes', 'DEMO-DATASET-PAYMENT-'.$suffix)->exists()) {
            return;
        }
        $customer = Customer::query()->where('code', $customerCode)->firstOrFail();
        $allocations = collect($lines)->map(fn ($line) => ['sales_invoice_id' => $invoices[$line[0]]->id, 'amount' => $line[1]])->all();
        $receipt = app(CreatePaymentReceiptAction::class)->handle(['customer_id' => $customer->id, 'payment_type' => 'invoice', 'payment_date' => '2026-03-20', 'amount' => $amount, 'payment_method' => 'bank_transfer', 'reference_number' => 'DEMO-BANK-'.$suffix, 'notes' => 'DEMO-DATASET-PAYMENT-'.$suffix, 'allocations' => $allocations]);
        app(ConfirmPaymentReceiptAction::class)->handle($receipt);
    }
}
