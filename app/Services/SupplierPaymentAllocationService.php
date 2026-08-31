<?php

namespace App\Services;

use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierPaymentAllocationService
{
    /** @return list<array{supplier_invoice_id:int, amount:string}> */
    public function validate(array $data, ?int $excludePaymentId = null): array
    {
        if (($data['payment_type'] ?? 'invoice') === 'advance') {
            return [];
        }

        $allocations = $this->normalizeInput($data);

        if ($allocations === []) {
            throw ValidationException::withMessages(['allocations' => 'يجب توزيع مبلغ الدفعة على فاتورة مورد واحدة على الأقل.']);
        }

        $invoiceIds = collect($allocations)->pluck('supplier_invoice_id');
        if ($invoiceIds->unique()->count() !== $invoiceIds->count()) {
            throw ValidationException::withMessages(['allocations' => 'لا يمكن تكرار الفاتورة نفسها في توزيع الدفعة.']);
        }

        $allocatedTotal = collect($allocations)->reduce(
            fn (string $total, array $allocation): string => bcadd($total, $allocation['amount'], 2),
            '0.00',
        );

        if (bccomp($allocatedTotal, (string) ($data['amount'] ?? '0'), 2) !== 0) {
            throw ValidationException::withMessages(['allocations' => 'يجب أن يساوي إجمالي التوزيع إجمالي مبلغ الدفعة.']);
        }

        $invoices = SupplierInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($allocations as $allocation) {
            $invoice = $invoices->get($allocation['supplier_invoice_id']);

            if (! $invoice || $invoice->trashed()) {
                throw ValidationException::withMessages(['allocations' => 'إحدى فواتير المورد المحددة غير موجودة أو محذوفة.']);
            }

            if ((int) $invoice->supplier_id !== (int) ($data['supplier_id'] ?? 0)) {
                throw ValidationException::withMessages(['allocations' => 'إحدى الفواتير لا تخص المورد المحدد.']);
            }

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['allocations' => 'لا يمكن السداد على فاتورة مورد ملغاة.']);
            }

            $outstanding = $this->outstandingAmount($invoice, $excludePaymentId);
            if (bccomp($allocation['amount'], $outstanding, 2) > 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'التوزيع على الفاتورة '.$invoice->invoice_number
                        .' يتجاوز المتبقي وقدره '.number_format((float) $outstanding, 2).' ج.م.',
                ]);
            }
        }

        return $allocations;
    }

    /** @return list<array{supplier_invoice_id:int, amount:string}> */
    public function validatePayment(SupplierPayment $payment): array
    {
        $this->ensureLegacyAllocation($payment);

        return $this->validate([
            'supplier_id' => $payment->supplier_id,
            'supplier_invoice_id' => $payment->supplier_invoice_id,
            'payment_type' => $payment->payment_type,
            'amount' => $payment->amount,
            'allocations' => $payment->allocations()
                ->get(['supplier_invoice_id', 'amount'])
                ->map(fn (SupplierPaymentAllocation $allocation): array => [
                    'supplier_invoice_id' => $allocation->supplier_invoice_id,
                    'amount' => (string) $allocation->amount,
                ])->all(),
        ], $payment->id);
    }

    /** @param list<array{supplier_invoice_id:int, amount:string}> $allocations */
    public function persist(SupplierPayment $payment, array $allocations): void
    {
        $invoiceIds = collect($allocations)->pluck('supplier_invoice_id')->all();

        $payment->allocations()
            ->when($invoiceIds !== [], fn ($query) => $query->whereNotIn('supplier_invoice_id', $invoiceIds))
            ->delete();

        foreach ($allocations as $allocation) {
            $payment->allocations()->updateOrCreate(
                ['supplier_invoice_id' => $allocation['supplier_invoice_id']],
                ['amount' => $allocation['amount']],
            );
        }

        $attributes = [
            'supplier_invoice_id' => count($allocations) === 1 ? $allocations[0]['supplier_invoice_id'] : null,
        ];
        if ($payment->payment_type === 'invoice') {
            $attributes['purchase_order_id'] = count($allocations) === 1
                ? SupplierInvoice::query()->whereKey($allocations[0]['supplier_invoice_id'])->value('purchase_order_id')
                : null;
        }
        $payment->update($attributes);
    }

    public function ensureLegacyAllocation(SupplierPayment $payment): void
    {
        if ($payment->payment_type === 'advance' || ! $payment->supplier_invoice_id || $payment->allocations()->exists()) {
            return;
        }

        $payment->allocations()->firstOrCreate(
            ['supplier_invoice_id' => $payment->supplier_invoice_id],
            ['amount' => $payment->amount],
        );
    }

    public function paidAmountForInvoice(int $invoiceId, ?int $excludePaymentId = null): string
    {
        $allocated = (string) SupplierPaymentAllocation::query()
            ->join('supplier_payments', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
            ->where('supplier_payment_allocations.supplier_invoice_id', $invoiceId)
            ->where('supplier_payments.status', SupplierPaymentStatus::CONFIRMED->value)
            ->whereNull('supplier_payments.deleted_at')
            ->when($excludePaymentId, fn ($query) => $query->where('supplier_payments.id', '!=', $excludePaymentId))
            ->sum('supplier_payment_allocations.amount');

        $legacy = (string) SupplierPayment::query()
            ->where('supplier_invoice_id', $invoiceId)
            ->where('status', SupplierPaymentStatus::CONFIRMED)
            ->when($excludePaymentId, fn ($query) => $query->whereKeyNot($excludePaymentId))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('supplier_payment_allocations')
                ->whereColumn('supplier_payment_allocations.supplier_payment_id', 'supplier_payments.id'))
            ->sum('amount');

        return bcadd($allocated, $legacy, 2);
    }

    public function outstandingAmount(SupplierInvoice $invoice, ?int $excludePaymentId = null): string
    {
        $remaining = bcsub((string) $invoice->total, $this->paidAmountForInvoice($invoice->id, $excludePaymentId), 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function availableInvoices(int $supplierId, ?int $excludePaymentId = null): Collection
    {
        return SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(function (SupplierInvoice $invoice) use ($excludePaymentId): array {
                $paid = $this->paidAmountForInvoice($invoice->id, $excludePaymentId);
                $remaining = bcsub((string) $invoice->total, $paid, 2);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d') ?? '',
                    'total_amount' => (float) $invoice->total,
                    'paid_amount' => (float) $paid,
                    'remaining_amount' => max(0, (float) $remaining),
                ];
            })
            ->filter(fn (array $invoice): bool => $invoice['remaining_amount'] > 0)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function breakdown(SupplierPayment $payment): Collection
    {
        $allocations = $payment->allocations()->with('supplierInvoice')->get();

        if ($allocations->isEmpty() && $payment->supplier_invoice_id) {
            $legacy = new SupplierPaymentAllocation([
                'supplier_invoice_id' => $payment->supplier_invoice_id,
                'amount' => $payment->amount,
            ]);
            $legacy->setRelation('supplierInvoice', $payment->invoice()->first());
            $allocations->push($legacy);
        }

        return $allocations->filter->supplierInvoice->map(function (SupplierPaymentAllocation $allocation) use ($payment): array {
            $invoice = $allocation->supplierInvoice;
            $previousPaid = $this->paidAmountForInvoice($invoice->id, $payment->id);
            $applied = $payment->status === SupplierPaymentStatus::CONFIRMED && ! $payment->trashed()
                ? (string) $allocation->amount
                : '0.00';
            $remaining = bcsub(bcsub((string) $invoice->total, $previousPaid, 2), $applied, 2);

            return [
                'invoice' => $invoice,
                'invoice_total' => (string) $invoice->total,
                'previous_paid' => $previousPaid,
                'allocation_amount' => (string) $allocation->amount,
                'remaining' => bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining,
            ];
        });
    }

    /** @param iterable<int> $invoiceIds */
    public function refreshInvoices(iterable $invoiceIds): void
    {
        foreach (collect($invoiceIds)->filter()->unique()->sort()->values() as $invoiceId) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->find($invoiceId);
            if (! $invoice || $invoice->status === 'cancelled') {
                continue;
            }

            $paid = $this->paidAmountForInvoice($invoice->id);
            $remaining = bcsub((string) $invoice->total, $paid, 2);
            $status = bccomp($remaining, '0.00', 2) <= 0
                ? 'paid'
                : (bccomp($paid, '0.00', 2) > 0 ? 'partially_paid' : 'unpaid');

            $invoice->update([
                'paid_amount' => $paid,
                'remaining_amount' => bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining,
                'status' => $status,
            ]);
        }
    }

    /** @return list<array{supplier_invoice_id:int, amount:string}> */
    private function normalizeInput(array $data): array
    {
        $raw = $data['allocations'] ?? [];

        if ($raw === [] && ! empty($data['supplier_invoice_id'])) {
            $raw = [[
                'supplier_invoice_id' => $data['supplier_invoice_id'],
                'amount' => $data['amount'] ?? '0',
            ]];
        }

        return collect($raw)
            ->map(fn (array $allocation): array => [
                'supplier_invoice_id' => (int) ($allocation['supplier_invoice_id'] ?? 0),
                'amount' => bcadd((string) ($allocation['amount'] ?? '0'), '0', 2),
            ])
            ->filter(fn (array $allocation): bool => $allocation['supplier_invoice_id'] > 0 && bccomp($allocation['amount'], '0.00', 2) > 0)
            ->values()
            ->all();
    }
}
