<?php

namespace App\Actions\SalesReturn;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesReturnStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\Accounting\DocumentPostingService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSalesReturnAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $ledger,
        private readonly StockService $stockService,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(SalesReturn $salesReturn): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            if ($salesReturn->status === SalesReturnStatus::CANCELLED) {
                return $salesReturn->load(['salesInvoice', 'customer', 'items']);
            }

            if ($salesReturn->status !== SalesReturnStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء إلا مرتجعات المبيعات المؤكدة.',
                ]);
            }

            SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->sales_invoice_id);

            $salesReturn->load('items');
            $this->stockService->validateAvailability($salesReturn->items);

            if (CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::SALES_RETURN_REVERSAL)
                ->where('reference_id', $salesReturn->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'تم تسجيل عكس لهذا المرتجع بالفعل.',
                ]);
            }

            $salesReturn->update(['status' => SalesReturnStatus::CANCELLED]);
            $this->stockService->createSalesReturnReversals($salesReturn);
            $this->ledger->handle([
                'customer_id' => $salesReturn->customer_id,
                'reference_type' => LedgerReferenceType::SALES_RETURN_REVERSAL,
                'reference_id' => $salesReturn->id,
                'reference_number' => $salesReturn->return_number,
                'transaction_date' => now()->toDateString(),
                'description' => 'إلغاء مرتجع مبيعات',
                'debit' => $salesReturn->total_amount,
                'credit' => 0,
                'created_by' => auth()->id(),
            ]);
            $this->accounting->reverse($salesReturn, 'sales_return', now()->toDateString());

            return $salesReturn->fresh(['salesInvoice', 'customer', 'items']);
        });
    }
}
