<?php

use App\Actions\PaymentReceipt\CancelPaymentReceiptAction;
use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\PaymentReceipt\UpdatePaymentReceiptAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function paymentReceiptTestInvoice(string $total = '10000.00'): SalesInvoice
{
    $customer = Customer::create([
        'code' => 'CUS-PAY-1',
        'name' => 'عميل اختبار الدفعات',
        'phone' => '01000000001',
        'credit_limit' => 50000,
        'status' => 'active',
    ]);

    return SalesInvoice::create([
        'invoice_number' => 'INV-PAY-1',
        'customer_id' => $customer->id,
        'invoice_date' => now()->toDateString(),
        'status' => SalesInvoiceStatus::CONFIRMED,
        'total_quantity' => 1,
        'subtotal' => $total,
        'total_amount' => $total,
    ]);
}

function paymentReceiptData(SalesInvoice $invoice, string $amount): array
{
    return [
        'customer_id' => $invoice->customer_id,
        'sales_invoice_id' => $invoice->id,
        'payment_type' => 'invoice',
        'payment_date' => now()->toDateString(),
        'amount' => $amount,
        'payment_method' => 'cash',
        'reference_number' => null,
        'notes' => null,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a valid invoice payment as a draft', function () {
    $invoice = paymentReceiptTestInvoice();

    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '6000.00'));

    expect($receipt->status)->toBe(PaymentReceiptStatus::DRAFT)
        ->and((string) $receipt->amount)->toBe('6000.00')
        ->and($receipt->sales_invoice_id)->toBe($invoice->id);
});

it('confirms a valid payment and posts its effect exactly once', function () {
    $invoice = paymentReceiptTestInvoice();
    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '6000.00'));

    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    expect($receipt->fresh()->status)->toBe(PaymentReceiptStatus::CONFIRMED)
        ->and($invoice->fresh()->remaining_amount)->toBe(4000.0)
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
            ->where('reference_id', $receipt->id)
            ->count())->toBe(1);
});

it('rejects confirmation above the latest invoice remaining amount atomically', function () {
    $invoice = paymentReceiptTestInvoice();
    $existing = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '4000.00'));
    app(ConfirmPaymentReceiptAction::class)->handle($existing);

    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '6000.00'));
    $receipt->update(['amount' => '6000.01']);

    expect(fn () => app(ConfirmPaymentReceiptAction::class)->handle($receipt))
        ->toThrow(ValidationException::class);

    expect($receipt->fresh()->status)->toBe(PaymentReceiptStatus::DRAFT)
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
            ->where('reference_id', $receipt->id)
            ->exists())->toBeFalse();
});

it('rejects an invoice that belongs to another customer during confirmation', function () {
    $invoice = paymentReceiptTestInvoice();
    $otherCustomer = Customer::create([
        'code' => 'CUS-PAY-2',
        'name' => 'عميل آخر',
        'phone' => '01000000002',
        'status' => 'active',
    ]);

    $receipt = PaymentReceipt::create([
        ...paymentReceiptData($invoice, '100.00'),
        'receipt_number' => 'RCPT-WRONG-CUSTOMER',
        'customer_id' => $otherCustomer->id,
        'status' => PaymentReceiptStatus::DRAFT,
        'created_by' => auth()->id(),
    ]);

    expect(fn () => app(ConfirmPaymentReceiptAction::class)->handle($receipt))
        ->toThrow(ValidationException::class);

    expect($receipt->fresh()->status)->toBe(PaymentReceiptStatus::DRAFT);
});

it('rejects updating a draft payment above the invoice remaining amount', function () {
    $invoice = paymentReceiptTestInvoice();
    $existing = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '4000.00'));
    app(ConfirmPaymentReceiptAction::class)->handle($existing);

    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '5000.00'));
    $update = paymentReceiptData($invoice, '6000.01');

    expect(fn () => app(UpdatePaymentReceiptAction::class)->handle($receipt, $update))
        ->toThrow(ValidationException::class);

    expect((string) $receipt->fresh()->amount)->toBe('5000.00');
});

it('treats confirming the same payment twice as an idempotent operation', function () {
    $invoice = paymentReceiptTestInvoice();
    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '1000.00'));

    $action = app(ConfirmPaymentReceiptAction::class);
    $action->handle($receipt);
    $action->handle($receipt);

    expect(CustomerLedgerEntry::query()
        ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
        ->where('reference_id', $receipt->id)
        ->count())->toBe(1)
        ->and($invoice->fresh()->remaining_amount)->toBe(9000.0);
});

it('treats cancelling the same payment twice as an idempotent operation', function () {
    $invoice = paymentReceiptTestInvoice();
    $receipt = app(CreatePaymentReceiptAction::class)
        ->handle(paymentReceiptData($invoice, '1000.00'));
    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    $action = app(CancelPaymentReceiptAction::class);
    $action->handle($receipt);
    $action->handle($receipt);

    expect($receipt->fresh()->status)->toBe(PaymentReceiptStatus::CANCELLED)
        ->and(CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT_REVERSAL)
            ->where('reference_id', $receipt->id)
            ->count())->toBe(1)
        ->and($invoice->fresh()->remaining_amount)->toBe(10000.0);
});
