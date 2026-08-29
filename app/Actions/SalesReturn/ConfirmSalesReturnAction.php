<?php

namespace App\Actions\SalesReturn;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\Accounting\DocumentPostingService;
use App\Services\SalesReturnService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSalesReturnAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $ledger,
        private readonly SalesReturnService $salesReturnService,
        private readonly StockService $stockService,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(SalesReturn $salesReturn): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            if ($salesReturn->status === SalesReturnStatus::CONFIRMED) {
                return $salesReturn->load(['salesInvoice', 'customer', 'items']);
            }

            if ($salesReturn->status !== SalesReturnStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد إلا مرتجعات المبيعات المسودة.',
                ]);
            }

            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->sales_invoice_id);

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => 'لم تعد فاتورة المبيعات في حالة تسمح بتأكيد المرتجع.',
                ]);
            }

            $salesReturn->load('items');
            $prepared = $this->salesReturnService->prepareItems(
                $invoice,
                $salesReturn->items->map(fn ($item) => [
                    'sales_invoice_item_id' => $item->sales_invoice_item_id,
                    'quantity' => $item->quantity,
                ])->all(),
                $salesReturn->id,
                [SalesReturnStatus::CONFIRMED],
            );

            if (CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::SALES_RETURN)
                ->where('reference_id', $salesReturn->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'تم ترحيل الأثر المالي لهذا المرتجع بالفعل.',
                ]);
            }

            $salesReturn->update([
                'status' => SalesReturnStatus::CONFIRMED,
                'total_quantity' => $prepared['total_quantity'],
                'total_amount' => $prepared['total_amount'],
            ]);
            $this->salesReturnService->replaceItems($salesReturn, $prepared['items']);
            $salesReturn->load('items');

            $this->stockService->createSalesReturnMovements($salesReturn);
            $this->ledger->handle([
                'customer_id' => $salesReturn->customer_id,
                'reference_type' => LedgerReferenceType::SALES_RETURN,
                'reference_id' => $salesReturn->id,
                'reference_number' => $salesReturn->return_number,
                'transaction_date' => $salesReturn->return_date,
                'description' => 'مرتجع مبيعات للفاتورة '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => $salesReturn->total_amount,
                'created_by' => auth()->id(),
            ]);
            $this->accounting->salesReturn($salesReturn->fresh());

            return $salesReturn->fresh(['salesInvoice', 'customer', 'items']);
        });
    }
}
