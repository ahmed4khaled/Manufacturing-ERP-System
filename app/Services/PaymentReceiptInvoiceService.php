<?php

namespace App\Services;

use App\Models\SalesInvoice;

class PaymentReceiptInvoiceService
{
    public function __construct(
        private readonly PaymentReceiptAllocationService $allocations,
    ) {}

    /**
     * Validate an invoice payment against the latest database state.
     *
     * The caller must run inside a database transaction. Locking the invoice
     * serializes payments for the same invoice without blocking unrelated ones.
     */
    public function validate(array $data, ?int $excludeReceiptId = null): ?SalesInvoice
    {
        $validated = $this->allocations->validate($data, $excludeReceiptId);

        if ($validated === []) {
            return null;
        }

        return SalesInvoice::query()->find($validated[0]['sales_invoice_id']);
    }
}
