<?php

namespace App\Enums;

enum LedgerReferenceType: string
{
    case SALES_INVOICE = 'sales_invoice';
    case PAYMENT_RECEIPT = 'payment_receipt';
    case SALES_RETURN = 'sales_return';
    case SALES_INVOICE_REVERSAL = 'sales_invoice_reversal';
    case PAYMENT_RECEIPT_REVERSAL = 'payment_receipt_reversal';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::SALES_INVOICE => 'Sales Invoice',
            self::PAYMENT_RECEIPT => 'Payment Receipt',
            self::SALES_RETURN => 'Sales Return',
            self::SALES_INVOICE_REVERSAL => 'Sales Invoice Reversal',
            self::PAYMENT_RECEIPT_REVERSAL => 'Payment Receipt Reversal',
            self::MANUAL_ADJUSTMENT => 'Manual Adjustment',
        };
    }
}
