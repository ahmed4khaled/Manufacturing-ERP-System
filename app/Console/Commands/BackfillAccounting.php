<?php

namespace App\Console\Commands;

use App\Models\PaymentReceipt;
use App\Models\ProductionOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\Accounting\DocumentPostingService;
use Illuminate\Console\Command;
use Throwable;

class BackfillAccounting extends Command
{
    protected $signature = 'accounting:backfill {--post : Create the missing idempotent journal entries}';

    protected $description = 'Audit or post historical ERP documents to the accounting journal without changing operational data';

    public function handle(DocumentPostingService $posting): int
    {
        if (! $this->option('post')) {
            $this->table(['Document', 'Eligible'], [
                ['Sales invoices', SalesInvoice::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->count()],
                ['Sales returns', SalesReturn::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->count()],
                ['Payment receipts', PaymentReceipt::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->count()],
                ['Supplier invoices', SupplierInvoice::withTrashed()->count()], ['Supplier payments', SupplierPayment::withTrashed()->count()],
                ['Purchase returns', PurchaseReturn::withTrashed()->where('status', 'confirmed')->count()], ['Production orders', ProductionOrder::withTrashed()->where('status', 'completed')->count()],
            ]);
            $this->info('Dry run only. Re-run with --post to create missing entries.');

            return self::SUCCESS;
        }

        $posted = 0;
        $failed = 0;
        $run = function (string $label, iterable $models, callable $callback) use (&$posted, &$failed): void {
            foreach ($models as $model) {
                try {
                    $callback($model);
                    $posted++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("{$label} #{$model->getKey()}: {$exception->getMessage()}");
                }
            }
        };
        $run('Sales invoice', SalesInvoice::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->orderBy('invoice_date')->cursor(), function ($invoice) use ($posting): void {
            $posting->salesInvoice($invoice);
            if ($invoice->status->value === 'cancelled') {
                $posting->reverse($invoice, 'sales_invoice', $invoice->updated_at->toDateString());
            }
        });
        $run('Sales return', SalesReturn::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->orderBy('return_date')->cursor(), function ($return) use ($posting): void {
            $posting->salesReturn($return);
            if ($return->status->value === 'cancelled') {
                $posting->reverse($return, 'sales_return', $return->updated_at->toDateString());
            }
        });
        $run('Payment receipt', PaymentReceipt::withTrashed()->whereIn('status', ['confirmed', 'cancelled'])->orderBy('payment_date')->cursor(), function ($receipt) use ($posting): void {
            $posting->customerPayment($receipt);
            if ($receipt->status->value === 'cancelled') {
                $posting->reverse($receipt, 'customer_payment', $receipt->updated_at->toDateString());
            }
        });
        $run('Supplier invoice', SupplierInvoice::withTrashed()->orderBy('invoice_date')->cursor(), fn ($invoice) => $posting->supplierInvoice($invoice));
        $run('Supplier payment', SupplierPayment::withTrashed()->orderBy('payment_date')->cursor(), fn ($payment) => $posting->supplierPayment($payment));
        $run('Purchase return', PurchaseReturn::withTrashed()->where('status', 'confirmed')->orderBy('return_date')->cursor(), fn ($return) => $posting->purchaseReturn($return));
        $run('Production order', ProductionOrder::withTrashed()->where('status', 'completed')->orderBy('closed_at')->cursor(), fn ($order) => $posting->production($order));
        $this->info("Processed {$posted}; failed {$failed}. Re-running is safe and does not duplicate entries.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
