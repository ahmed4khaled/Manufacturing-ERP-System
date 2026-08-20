<?php

namespace App\Enums;

enum LedgerReferenceType: string
{
    case SALES_INVOICE = 'sales_invoice';
    case PAYMENT_RECEIPT = 'payment_receipt';
    case SALES_RETURN = 'sales_return';
    case SALES_RETURN_REVERSAL = 'sales_return_reversal';
    case SALES_INVOICE_REVERSAL = 'sales_invoice_reversal';
    case PAYMENT_RECEIPT_REVERSAL = 'payment_receipt_reversal';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::SALES_INVOICE => 'فاتورة مبيعات',
            self::PAYMENT_RECEIPT => 'سند قبض',
            self::SALES_RETURN => 'مرتجع مبيعات',
            self::SALES_RETURN_REVERSAL => 'عكس مرتجع مبيعات',
            self::SALES_INVOICE_REVERSAL => 'عكس فاتورة مبيعات',
            self::PAYMENT_RECEIPT_REVERSAL => 'عكس سند قبض',
            self::MANUAL_ADJUSTMENT => 'تسوية يدوية',
        };
    }
}
