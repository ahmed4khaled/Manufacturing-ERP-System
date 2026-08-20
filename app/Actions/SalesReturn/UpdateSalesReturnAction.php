<?php

namespace App\Actions\SalesReturn;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSalesReturnAction
{
    public function __construct(
        private readonly SalesReturnService $salesReturnService,
    ) {}

    public function handle(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            if ($salesReturn->status !== SalesReturnStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعديل إلا مرتجعات المبيعات المسودة.',
                ]);
            }

            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->sales_invoice_id);

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => 'لم تعد فاتورة المبيعات في حالة تسمح بالمرتجع.',
                ]);
            }

            $prepared = $this->salesReturnService->prepareItems(
                $invoice,
                $data['items'],
                $salesReturn->id,
            );

            $salesReturn->update([
                'return_date' => $data['return_date'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'total_quantity' => $prepared['total_quantity'],
                'total_amount' => $prepared['total_amount'],
            ]);

            $this->salesReturnService->replaceItems($salesReturn, $prepared['items']);

            return $salesReturn->fresh(['salesInvoice', 'customer', 'items']);
        });
    }
}
