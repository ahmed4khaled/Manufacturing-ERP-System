<?php

namespace App\Services;

use App\Enums\PaymentReceiptStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\SalesInvoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentReceiptAllocationService
{
    /**
     * @return list<array{sales_invoice_id:int, amount:string}>
     */
    public function validate(array $data, ?int $excludeReceiptId = null): array
    {
        if (($data['payment_type'] ?? 'invoice') === 'advance') {
            return [];
        }

        $allocations = $this->normalizeInput($data);

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'يجب توزيع مبلغ السند على فاتورة واحدة على الأقل.',
            ]);
        }

        $invoiceIds = collect($allocations)->pluck('sales_invoice_id');
        if ($invoiceIds->unique()->count() !== $invoiceIds->count()) {
            throw ValidationException::withMessages([
                'allocations' => 'لا يمكن تكرار الفاتورة نفسها في توزيع السند.',
            ]);
        }

        $allocatedTotal = collect($allocations)->reduce(
            fn (string $total, array $allocation): string => bcadd($total, $allocation['amount'], 2),
            '0.00',
        );

        if (bccomp($allocatedTotal, (string) ($data['amount'] ?? '0'), 2) !== 0) {
            throw ValidationException::withMessages([
                'allocations' => 'يجب أن يساوي إجمالي التوزيع إجمالي مبلغ السند.',
            ]);
        }

        /** @var Collection<int, SalesInvoice> $invoices */
        $invoices = SalesInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($allocations as $allocation) {
            $invoice = $invoices->get($allocation['sales_invoice_id']);

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'allocations' => 'إحدى فواتير المبيعات المحددة غير موجودة أو محذوفة.',
                ]);
            }

            if ((int) $invoice->customer_id !== (int) ($data['customer_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'allocations' => 'إحدى الفواتير لا تخص العميل المحدد.',
                ]);
            }

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'allocations' => 'لا يمكن السداد إلا على فواتير مبيعات مؤكدة.',
                ]);
            }

            $outstanding = $this->outstandingAmount($invoice, $excludeReceiptId);

            if (bccomp($allocation['amount'], $outstanding, 2) > 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'التوزيع على الفاتورة '.$invoice->invoice_number
                        .' يتجاوز المتبقي وقدره '.number_format((float) $outstanding, 2).' ج.م.',
                ]);
            }
        }

        return $allocations;
    }

    /**
     * @return list<array{sales_invoice_id:int, amount:string}>
     */
    public function validateReceipt(PaymentReceipt $receipt): array
    {
        $this->ensureLegacyAllocation($receipt);

        return $this->validate([
            'customer_id' => $receipt->customer_id,
            'sales_invoice_id' => $receipt->sales_invoice_id,
            'payment_type' => $receipt->payment_type,
            'amount' => $receipt->amount,
            'allocations' => $receipt->allocations()
                ->get(['sales_invoice_id', 'amount'])
                ->map(fn (PaymentReceiptAllocation $allocation): array => [
                    'sales_invoice_id' => $allocation->sales_invoice_id,
                    'amount' => (string) $allocation->amount,
                ])->all(),
        ], $receipt->id);
    }

    /** @param list<array{sales_invoice_id:int, amount:string}> $allocations */
    public function persist(PaymentReceipt $receipt, array $allocations): void
    {
        $invoiceIds = collect($allocations)->pluck('sales_invoice_id')->all();

        $receipt->allocations()
            ->when($invoiceIds !== [], fn ($query) => $query->whereNotIn('sales_invoice_id', $invoiceIds))
            ->when($invoiceIds === [], fn ($query) => $query)
            ->delete();

        foreach ($allocations as $allocation) {
            $receipt->allocations()->updateOrCreate(
                ['sales_invoice_id' => $allocation['sales_invoice_id']],
                ['amount' => $allocation['amount']],
            );
        }

        $receipt->update([
            'sales_invoice_id' => count($allocations) === 1
                ? $allocations[0]['sales_invoice_id']
                : null,
        ]);
    }

    public function ensureLegacyAllocation(PaymentReceipt $receipt): void
    {
        if ($receipt->payment_type === 'advance' || ! $receipt->sales_invoice_id || $receipt->allocations()->exists()) {
            return;
        }

        $receipt->allocations()->firstOrCreate(
            ['sales_invoice_id' => $receipt->sales_invoice_id],
            ['amount' => $receipt->amount],
        );
    }

    public function paidAmountForInvoice(int $invoiceId, ?int $excludeReceiptId = null): string
    {
        $allocated = (string) PaymentReceiptAllocation::query()
            ->join('payment_receipts', 'payment_receipts.id', '=', 'payment_receipt_allocations.payment_receipt_id')
            ->where('payment_receipt_allocations.sales_invoice_id', $invoiceId)
            ->where('payment_receipts.status', PaymentReceiptStatus::CONFIRMED->value)
            ->whereNull('payment_receipts.deleted_at')
            ->when($excludeReceiptId, fn ($query) => $query->where('payment_receipts.id', '!=', $excludeReceiptId))
            ->sum('payment_receipt_allocations.amount');

        $legacy = (string) PaymentReceipt::query()
            ->where('sales_invoice_id', $invoiceId)
            ->where('status', PaymentReceiptStatus::CONFIRMED)
            ->when($excludeReceiptId, fn ($query) => $query->whereKeyNot($excludeReceiptId))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('payment_receipt_allocations')
                ->whereColumn('payment_receipt_allocations.payment_receipt_id', 'payment_receipts.id'))
            ->sum('amount');

        return bcadd($allocated, $legacy, 2);
    }

    public function outstandingAmount(SalesInvoice $invoice, ?int $excludeReceiptId = null): string
    {
        $returned = (string) $invoice->salesReturns()
            ->where('status', SalesReturnStatus::CONFIRMED)
            ->sum('total_amount');
        $net = bcsub((string) $invoice->total_amount, $returned, 2);
        $remaining = bcsub($net, $this->paidAmountForInvoice($invoice->id, $excludeReceiptId), 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function availableInvoices(int $customerId, ?int $excludeReceiptId = null): Collection
    {
        return SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', SalesInvoiceStatus::CONFIRMED)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(function (SalesInvoice $invoice) use ($excludeReceiptId): array {
                $returned = (string) $invoice->salesReturns()
                    ->where('status', SalesReturnStatus::CONFIRMED)
                    ->sum('total_amount');
                $paid = $this->paidAmountForInvoice($invoice->id, $excludeReceiptId);
                $remaining = bcsub(bcsub((string) $invoice->total_amount, $returned, 2), $paid, 2);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d') ?? '',
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $paid,
                    'returned_amount' => (float) $returned,
                    'remaining_amount' => max(0, (float) $remaining),
                ];
            })
            ->filter(fn (array $invoice): bool => $invoice['remaining_amount'] > 0)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function breakdown(PaymentReceipt $receipt): Collection
    {
        $allocations = $receipt->allocations()->with('salesInvoice')->get();

        if ($allocations->isEmpty() && $receipt->sales_invoice_id) {
            $legacy = new PaymentReceiptAllocation([
                'sales_invoice_id' => $receipt->sales_invoice_id,
                'amount' => $receipt->amount,
            ]);
            $legacy->setRelation('salesInvoice', $receipt->invoice()->first());
            $allocations->push($legacy);
        }

        return $allocations->filter->salesInvoice->map(function (PaymentReceiptAllocation $allocation) use ($receipt): array {
            $invoice = $allocation->salesInvoice;
            $previousPaid = $this->paidAmountForInvoice($invoice->id, $receipt->id);
            $returned = (string) $invoice->salesReturns()
                ->where('status', SalesReturnStatus::CONFIRMED)
                ->sum('total_amount');
            $applied = $receipt->status === PaymentReceiptStatus::CONFIRMED && ! $receipt->trashed()
                ? (string) $allocation->amount
                : '0.00';
            $remaining = bcsub(
                bcsub(bcsub((string) $invoice->total_amount, $returned, 2), $previousPaid, 2),
                $applied,
                2,
            );

            return [
                'invoice' => $invoice,
                'invoice_total' => (string) $invoice->total_amount,
                'previous_paid' => $previousPaid,
                'allocation_amount' => (string) $allocation->amount,
                'remaining' => bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining,
            ];
        });
    }

    /** @return list<array{sales_invoice_id:int, amount:string}> */
    private function normalizeInput(array $data): array
    {
        $raw = $data['allocations'] ?? [];

        if ($raw === [] && ! empty($data['sales_invoice_id'])) {
            $raw = [[
                'sales_invoice_id' => $data['sales_invoice_id'],
                'amount' => $data['amount'] ?? '0',
            ]];
        }

        return collect($raw)
            ->map(fn (array $allocation): array => [
                'sales_invoice_id' => (int) ($allocation['sales_invoice_id'] ?? 0),
                'amount' => bcadd((string) ($allocation['amount'] ?? '0'), '0', 2),
            ])
            ->filter(fn (array $allocation): bool => $allocation['sales_invoice_id'] > 0 && bccomp($allocation['amount'], '0.00', 2) > 0)
            ->values()
            ->all();
    }
}
