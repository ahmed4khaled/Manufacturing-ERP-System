<?php

namespace App\Services;

use App\Enums\PaymentReceiptStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use Illuminate\Validation\ValidationException;

class PaymentReceiptInvoiceService
{
    /**
     * Validate an invoice payment against the latest database state.
     *
     * The caller must run inside a database transaction. Locking the invoice
     * serializes payments for the same invoice without blocking unrelated ones.
     */
    public function validate(array $data, ?int $excludeReceiptId = null): ?SalesInvoice
    {
        if (($data['payment_type'] ?? 'invoice') === 'advance') {
            return null;
        }

        $invoiceId = $data['sales_invoice_id'] ?? null;

        if (! $invoiceId) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'يجب اختيار فاتورة عند تسجيل سداد فاتورة.',
            ]);
        }

        $invoice = SalesInvoice::query()
            ->lockForUpdate()
            ->find($invoiceId);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'فاتورة المبيعات المحددة غير موجودة.',
            ]);
        }

        if ((int) $invoice->customer_id !== (int) ($data['customer_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'الفاتورة لا تخص العميل المحدد.',
            ]);
        }

        if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => 'لا يمكن السداد إلا على فاتورة مبيعات مؤكدة.',
            ]);
        }

        $confirmedPayments = PaymentReceipt::query()
            ->where('sales_invoice_id', $invoice->id)
            ->where('status', PaymentReceiptStatus::CONFIRMED)
            ->when($excludeReceiptId, fn ($query) => $query->whereKeyNot($excludeReceiptId))
            ->lockForUpdate()
            ->get(['id', 'amount']);

        $paidAmount = $confirmedPayments->reduce(
            fn (string $total, PaymentReceipt $receipt) => bcadd($total, (string) $receipt->amount, 2),
            '0.00',
        );

        $returnedAmount = (string) $invoice->salesReturns()
            ->where('status', SalesReturnStatus::CONFIRMED)
            ->sum('total_amount');
        $netInvoiceAmount = bcsub((string) $invoice->total_amount, $returnedAmount, 2);
        $remainingAmount = bcsub($netInvoiceAmount, $paidAmount, 2);

        if (bccomp($remainingAmount, '0.00', 2) < 0) {
            $remainingAmount = '0.00';
        }

        if (bccomp((string) ($data['amount'] ?? '0'), $remainingAmount, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تسجيل دفعة أكبر من المبلغ المتبقي على الفاتورة. المتبقي: '
                    .number_format((float) $remainingAmount, 2)
                    .' ج.م.',
            ]);
        }

        return $invoice;
    }
}
