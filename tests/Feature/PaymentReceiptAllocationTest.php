<?php

use App\Actions\PaymentReceipt\CancelPaymentReceiptAction;
use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\PaymentReceipt\DeletePaymentReceiptAction;
use App\Actions\SalesInvoice\CancelSalesInvoiceAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptAllocation;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PaymentReceiptAllocationService;
use Illuminate\Validation\ValidationException;

function allocationCustomer(string $code = 'ALLOC-C'): Customer
{
    return Customer::create([
        'code' => $code,
        'name' => 'عميل توزيع الدفعات',
        'phone' => '01010000000',
        'credit_limit' => 100000,
        'status' => 'active',
    ]);
}

function allocationInvoice(Customer $customer, string $number, string $total, string $date, string $status = 'confirmed'): SalesInvoice
{
    return SalesInvoice::create([
        'invoice_number' => $number,
        'customer_id' => $customer->id,
        'invoice_date' => $date,
        'status' => $status,
        'total_quantity' => 1,
        'subtotal' => $total,
        'total_amount' => $total,
    ]);
}

function allocationReceiptData(Customer $customer, string $amount, array $allocations): array
{
    return [
        'customer_id' => $customer->id,
        'payment_type' => 'invoice',
        'payment_date' => now()->toDateString(),
        'amount' => $amount,
        'payment_method' => 'cash',
        'reference_number' => null,
        'notes' => null,
        'allocations' => $allocations,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates one receipt allocated across multiple invoices and posts one ledger entry', function () {
    $customer = allocationCustomer();
    $first = allocationInvoice($customer, 'ALLOC-INV-1', '5000.00', now()->subDays(2)->toDateString());
    $second = allocationInvoice($customer, 'ALLOC-INV-2', '10000.00', now()->subDay()->toDateString());
    $stockCount = StockMovement::count();

    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '8000.00', [
        ['sales_invoice_id' => $first->id, 'amount' => '5000.00'],
        ['sales_invoice_id' => $second->id, 'amount' => '3000.00'],
    ]));

    expect($receipt->sales_invoice_id)->toBeNull()
        ->and($receipt->allocations)->toHaveCount(2)
        ->and(PaymentReceipt::count())->toBe(1)
        ->and(CustomerLedgerEntry::count())->toBe(0);

    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    expect($first->fresh()->remaining_amount)->toBe(0.0)
        ->and($second->fresh()->remaining_amount)->toBe(7000.0)
        ->and(CustomerLedgerEntry::where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)->where('reference_id', $receipt->id)->count())->toBe(1)
        ->and((string) CustomerLedgerEntry::where('reference_id', $receipt->id)->value('credit'))->toBe('8000.00')
        ->and(StockMovement::count())->toBe($stockCount);
});

it('supports single-invoice and partial allocations while preserving the legacy invoice column', function () {
    $customer = allocationCustomer();
    $invoice = allocationInvoice($customer, 'ALLOC-PARTIAL', '20000.00', now()->toDateString());
    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '3000.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '3000.00'],
    ]));
    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    expect($receipt->fresh()->sales_invoice_id)->toBe($invoice->id)
        ->and($receipt->allocations)->toHaveCount(1)
        ->and($invoice->fresh()->paid_amount)->toBe(3000.0)
        ->and($invoice->fresh()->remaining_amount)->toBe(17000.0);
});

it('rejects invalid allocation totals duplicate invoices and over-allocation', function () {
    $customer = allocationCustomer();
    $invoice = allocationInvoice($customer, 'ALLOC-VALIDATE', '5000.00', now()->toDateString());

    expect(fn () => app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '4000.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '3000.00'],
    ])))->toThrow(ValidationException::class);

    expect(fn () => app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '6000.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '3000.00'],
        ['sales_invoice_id' => $invoice->id, 'amount' => '3000.00'],
    ])))->toThrow(ValidationException::class);

    expect(fn () => app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '5000.01', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '5000.01'],
    ])))->toThrow(ValidationException::class);

    expect(PaymentReceipt::count())->toBe(0)->and(PaymentReceiptAllocation::count())->toBe(0);
});

it('rejects invoices owned by another customer or in cancelled or deleted states', function () {
    $customer = allocationCustomer();
    $other = allocationCustomer('ALLOC-OTHER');
    $otherInvoice = allocationInvoice($other, 'ALLOC-OTHER-INV', '100.00', now()->toDateString());
    $cancelled = allocationInvoice($customer, 'ALLOC-CANCELLED', '100.00', now()->toDateString(), 'cancelled');
    $deleted = allocationInvoice($customer, 'ALLOC-DELETED', '100.00', now()->toDateString());
    $deleted->delete();

    foreach ([$otherInvoice, $cancelled, $deleted] as $invoice) {
        expect(fn () => app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '100.00', [
            ['sales_invoice_id' => $invoice->id, 'amount' => '100.00'],
        ])))->toThrow(ValidationException::class);
    }
});

it('rechecks outstanding amounts under invoice locks before confirmation', function () {
    $customer = allocationCustomer();
    $invoice = allocationInvoice($customer, 'ALLOC-LOCK', '1000.00', now()->toDateString());
    $first = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '600.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '600.00'],
    ]));
    $second = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '600.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '600.00'],
    ]));

    app(ConfirmPaymentReceiptAction::class)->handle($first);
    expect(fn () => app(ConfirmPaymentReceiptAction::class)->handle($second))->toThrow(ValidationException::class);
    expect($second->fresh()->status)->toBe(PaymentReceiptStatus::DRAFT)
        ->and($invoice->fresh()->remaining_amount)->toBe(400.0);
});

it('cancellation removes every allocation effect but reverses the customer payment once', function () {
    $customer = allocationCustomer();
    $first = allocationInvoice($customer, 'ALLOC-CAN-1', '500.00', now()->subDay()->toDateString());
    $second = allocationInvoice($customer, 'ALLOC-CAN-2', '700.00', now()->toDateString());
    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '1000.00', [
        ['sales_invoice_id' => $first->id, 'amount' => '500.00'],
        ['sales_invoice_id' => $second->id, 'amount' => '500.00'],
    ]));
    app(ConfirmPaymentReceiptAction::class)->handle($receipt);
    app(CancelPaymentReceiptAction::class)->handle($receipt);

    expect($first->fresh()->remaining_amount)->toBe(500.0)
        ->and($second->fresh()->remaining_amount)->toBe(700.0)
        ->and(CustomerLedgerEntry::where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)->where('reference_id', $receipt->id)->count())->toBe(1)
        ->and(CustomerLedgerEntry::where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT_REVERSAL)->where('reference_id', $receipt->id)->count())->toBe(1)
        ->and((float) CustomerLedgerEntry::where('customer_id', $customer->id)->sum('debit'))->toBe(1000.0)
        ->and((float) CustomerLedgerEntry::where('customer_id', $customer->id)->sum('credit'))->toBe(1000.0);
});

it('reads legacy receipts without changing their financial totals or relationships', function () {
    $customer = allocationCustomer();
    $invoice = allocationInvoice($customer, 'ALLOC-LEGACY', '5000.00', now()->toDateString());
    $receipt = PaymentReceipt::create([
        'receipt_number' => 'ALLOC-LEGACY-R',
        'customer_id' => $customer->id,
        'sales_invoice_id' => $invoice->id,
        'payment_type' => 'invoice',
        'payment_date' => now(),
        'amount' => '1250.00',
        'payment_method' => 'cash',
        'status' => PaymentReceiptStatus::CONFIRMED,
    ]);
    $before = ['count' => PaymentReceipt::count(), 'sum' => PaymentReceipt::sum('amount'), 'allocations' => PaymentReceiptAllocation::count()];

    expect($invoice->fresh()->paid_amount)->toBe(1250.0)
        ->and($receipt->invoice->id)->toBe($invoice->id);
    $breakdown = app(PaymentReceiptAllocationService::class)->breakdown($receipt);
    $this->get(route('payment-receipts.show', $receipt))->assertOk()->assertSee('ALLOC-LEGACY');
    $this->get(route('payment-receipts.print', $receipt))->assertOk()->assertSee('ALLOC-LEGACY');

    expect($breakdown)->toHaveCount(1)
        ->and($breakdown->first()['allocation_amount'])->toBe('1250.00')
        ->and(PaymentReceipt::count())->toBe($before['count'])
        ->and(PaymentReceipt::sum('amount'))->toEqual($before['sum'])
        ->and(PaymentReceiptAllocation::count())->toBe($before['allocations']);
});

it('prevents invoice cancellation while any confirmed allocation is active', function () {
    $customer = allocationCustomer();
    $first = allocationInvoice($customer, 'ALLOC-CANCEL-BLOCK-1', '500.00', now()->subDay()->toDateString());
    $second = allocationInvoice($customer, 'ALLOC-CANCEL-BLOCK-2', '500.00', now()->toDateString());
    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '600.00', [
        ['sales_invoice_id' => $first->id, 'amount' => '300.00'],
        ['sales_invoice_id' => $second->id, 'amount' => '300.00'],
    ]));
    app(ConfirmPaymentReceiptAction::class)->handle($receipt);

    expect(fn () => app(CancelSalesInvoiceAction::class)->handle($first))->toThrow(ValidationException::class);
});

it('shows and prints allocation details and exposes oldest-first auto allocation data', function () {
    $customer = allocationCustomer();
    $oldest = allocationInvoice($customer, 'ALLOC-OLD', '500.00', now()->subDays(5)->toDateString());
    $newest = allocationInvoice($customer, 'ALLOC-NEW', '700.00', now()->toDateString());
    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '800.00', [
        ['sales_invoice_id' => $oldest->id, 'amount' => '500.00'],
        ['sales_invoice_id' => $newest->id, 'amount' => '300.00'],
    ]));

    $this->get(route('payment-receipts.show', $receipt))->assertOk()->assertSee('تفاصيل توزيع الدفعة')->assertSee('ALLOC-OLD')->assertSee('ALLOC-NEW');
    $this->get(route('payment-receipts.print', $receipt))->assertOk()->assertSee('تفاصيل سداد الفواتير')->assertSee('ALLOC-OLD')->assertSee('ALLOC-NEW');
    $this->get(route('payment-receipts.create'))->assertOk()->assertSee('توزيع تلقائي');
    $response = $this->getJson(route('payment-receipts.customer-invoices', $customer));
    $response->assertOk();
    expect($response->json('invoices.0.id'))->toBe($oldest->id)
        ->and($response->json('invoices.1.id'))->toBe($newest->id);
});

it('soft deletion preserves allocation history', function () {
    $customer = allocationCustomer();
    $invoice = allocationInvoice($customer, 'ALLOC-SOFT', '100.00', now()->toDateString());
    $receipt = app(CreatePaymentReceiptAction::class)->handle(allocationReceiptData($customer, '100.00', [
        ['sales_invoice_id' => $invoice->id, 'amount' => '100.00'],
    ]));
    $allocationId = $receipt->allocations->first()->id;

    app(DeletePaymentReceiptAction::class)->handle($receipt);

    expect(PaymentReceipt::withTrashed()->find($receipt->id)->trashed())->toBeTrue()
        ->and(PaymentReceiptAllocation::find($allocationId))->not->toBeNull()
        ->and(PaymentReceiptAllocation::find($allocationId)->paymentReceipt->id)->toBe($receipt->id);
});
