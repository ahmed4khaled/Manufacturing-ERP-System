<?php

namespace App\Actions\SalesReturn;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\CodeGeneratorService;
use App\Services\SalesReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSalesReturnAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly SalesReturnService $salesReturnService,
    ) {}

    public function handle(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->find($data['sales_invoice_id']);

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => 'فاتورة المبيعات المحددة غير موجودة.',
                ]);
            }

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => 'لا يمكن إنشاء مرتجع إلا من فاتورة مبيعات مؤكدة.',
                ]);
            }

            $prepared = $this->salesReturnService->prepareItems($invoice, $data['items']);

            $salesReturn = SalesReturn::create([
                'return_number' => $this->codeGenerator->next('SRT'),
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'return_date' => $data['return_date'],
                'status' => SalesReturnStatus::DRAFT,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'total_quantity' => $prepared['total_quantity'],
                'total_amount' => $prepared['total_amount'],
                'created_by' => auth()->id(),
            ]);

            $this->salesReturnService->replaceItems($salesReturn, $prepared['items']);

            return $salesReturn->fresh(['salesInvoice', 'customer', 'items']);
        });
    }
}
