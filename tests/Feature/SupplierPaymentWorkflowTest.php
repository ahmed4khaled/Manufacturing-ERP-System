<?php

use App\Actions\Procurement\CancelSupplierPaymentAction;
use App\Actions\Procurement\ConfirmSupplierPaymentAction;
use App\Actions\Procurement\CreateSupplierPaymentAction;
use App\Actions\Procurement\DeleteSupplierPaymentAction;
use App\Enums\SupplierPaymentStatus;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Services\SupplierPaymentAllocationService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Validation\ValidationException;

function supplierPaymentSupplier(string $code = 'SP-WORKFLOW'): Supplier
{
    return Supplier::create(['code' => $code, 'name' => 'مورد اختبار المدفوعات', 'status' => 'active']);
}

function supplierPaymentInvoice(Supplier $supplier, string $number, string $total, string $date): SupplierInvoice
{
    return SupplierInvoice::create([
        'invoice_number' => $number,
        'supplier_id' => $supplier->id,
        'invoice_date' => $date,
        'subtotal' => $total,
        'discount' => 0,
        'tax' => 0,
        'total' => $total,
        'paid_amount' => 0,
        'remaining_amount' => $total,
        'status' => 'unpaid',
    ]);
}

function supplierPaymentData(Supplier $supplier, string $amount, array $allocations): array
{
    return [
        'supplier_id' => $supplier->id,
        'payment_type' => 'invoice',
        'payment_date' => '2026-08-30',
        'amount' => $amount,
        'payment_method' => 'cash',
        'reference' => null,
        'notes' => null,
        'allocations' => $allocations,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));
    $this->seed(AccountingSeeder::class);
});

it('creates a draft distributed over several supplier invoices and posts only on confirmation', function () {
    $supplier = supplierPaymentSupplier();
    $first = supplierPaymentInvoice($supplier, 'SP-INV-1', '500.00', '2026-08-28');
    $second = supplierPaymentInvoice($supplier, 'SP-INV-2', '900.00', '2026-08-29');

    $payment = app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '800.00', [
        ['supplier_invoice_id' => $first->id, 'amount' => '500.00'],
        ['supplier_invoice_id' => $second->id, 'amount' => '300.00'],
    ]));

    expect($payment->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($payment->supplier_invoice_id)->toBeNull()
        ->and($payment->allocations)->toHaveCount(2)
        ->and($first->fresh()->paid_amount)->toBe('0.00')
        ->and(JournalEntry::where('reference_type', SupplierPayment::class)->where('reference_id', $payment->id)->count())->toBe(0);

    app(ConfirmSupplierPaymentAction::class)->handle($payment);

    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::CONFIRMED)
        ->and($first->fresh()->paid_amount)->toBe('500.00')
        ->and($first->fresh()->remaining_amount)->toBe('0.00')
        ->and($first->fresh()->status)->toBe('paid')
        ->and($second->fresh()->paid_amount)->toBe('300.00')
        ->and($second->fresh()->remaining_amount)->toBe('600.00')
        ->and($second->fresh()->status)->toBe('partially_paid')
        ->and(JournalEntry::where('reference_type', SupplierPayment::class)->where('reference_id', $payment->id)->where('entry_type', 'supplier_payment')->count())->toBe(1);
});

it('cancels a confirmed supplier payment and restores every invoice with one accounting reversal', function () {
    $supplier = supplierPaymentSupplier();
    $first = supplierPaymentInvoice($supplier, 'SP-CAN-1', '400.00', '2026-08-28');
    $second = supplierPaymentInvoice($supplier, 'SP-CAN-2', '600.00', '2026-08-29');
    $payment = app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '700.00', [
        ['supplier_invoice_id' => $first->id, 'amount' => '400.00'],
        ['supplier_invoice_id' => $second->id, 'amount' => '300.00'],
    ]));
    app(ConfirmSupplierPaymentAction::class)->handle($payment);

    app(CancelSupplierPaymentAction::class)->handle($payment);
    app(CancelSupplierPaymentAction::class)->handle($payment->fresh());

    $original = JournalEntry::where('reference_type', SupplierPayment::class)
        ->where('reference_id', $payment->id)->where('entry_type', 'supplier_payment')->firstOrFail();
    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::CANCELLED)
        ->and($first->fresh()->paid_amount)->toBe('0.00')
        ->and($first->fresh()->remaining_amount)->toBe('400.00')
        ->and($second->fresh()->paid_amount)->toBe('0.00')
        ->and($second->fresh()->remaining_amount)->toBe('600.00')
        ->and($original->fresh()->reversed_by_id)->not->toBeNull()
        ->and(JournalEntry::where('reversal_of_id', $original->id)->count())->toBe(1);
});

it('rechecks remaining balances when competing drafts are confirmed', function () {
    $supplier = supplierPaymentSupplier();
    $invoice = supplierPaymentInvoice($supplier, 'SP-LOCK', '1000.00', '2026-08-30');
    $data = supplierPaymentData($supplier, '600.00', [['supplier_invoice_id' => $invoice->id, 'amount' => '600.00']]);
    $first = app(CreateSupplierPaymentAction::class)->handle($data);
    $second = app(CreateSupplierPaymentAction::class)->handle($data);

    app(ConfirmSupplierPaymentAction::class)->handle($first);

    expect(fn () => app(ConfirmSupplierPaymentAction::class)->handle($second))->toThrow(ValidationException::class)
        ->and($second->fresh()->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($invoice->fresh()->remaining_amount)->toBe('400.00');
});

it('rejects invalid allocations and invoices belonging to a different supplier', function () {
    $supplier = supplierPaymentSupplier();
    $other = supplierPaymentSupplier('SP-OTHER');
    $invoice = supplierPaymentInvoice($other, 'SP-OTHER-INV', '100.00', '2026-08-30');

    expect(fn () => app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '100.00', [
        ['supplier_invoice_id' => $invoice->id, 'amount' => '100.00'],
    ])))->toThrow(ValidationException::class);

    expect(fn () => app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '90.00', [
        ['supplier_invoice_id' => $invoice->id, 'amount' => '80.00'],
    ])))->toThrow(ValidationException::class);

    expect(SupplierPayment::count())->toBe(0)->and(SupplierPaymentAllocation::count())->toBe(0);
});

it('keeps a supplier payment draft when accounting period is closed', function () {
    $supplier = supplierPaymentSupplier();
    $invoice = supplierPaymentInvoice($supplier, 'SP-CLOSED', '250.00', '2026-08-30');
    $payment = app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '250.00', [
        ['supplier_invoice_id' => $invoice->id, 'amount' => '250.00'],
    ]));
    $year = FiscalYear::create(['name' => 'Supplier payment closed', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    FiscalPeriod::create(['fiscal_year_id' => $year->id, 'name' => 'Closed', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed']);

    expect(fn () => app(ConfirmSupplierPaymentAction::class)->handle($payment))->toThrow(ValidationException::class)
        ->and($payment->fresh()->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($invoice->fresh()->paid_amount)->toBe('0.00')
        ->and(JournalEntry::where('reference_type', SupplierPayment::class)->where('reference_id', $payment->id)->count())->toBe(0);
});

it('reads confirmed legacy payments and preserves their invoice effect', function () {
    $supplier = supplierPaymentSupplier();
    $invoice = supplierPaymentInvoice($supplier, 'SP-LEGACY-INV', '500.00', '2026-08-30');
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-LEGACY-PAY', 'supplier_id' => $supplier->id,
        'supplier_invoice_id' => $invoice->id, 'payment_type' => 'invoice',
        'payment_date' => '2026-08-30', 'amount' => '125.00', 'payment_method' => 'cash',
        'status' => SupplierPaymentStatus::CONFIRMED,
    ]);

    $service = app(SupplierPaymentAllocationService::class);
    expect($service->paidAmountForInvoice($invoice->id))->toBe('125.00')
        ->and($service->breakdown($payment))->toHaveCount(1)
        ->and($service->breakdown($payment)->first()['allocation_amount'])->toBe('125.00');
});

it('renders supplier payment pages ajax invoices and the customer-style supplier invoice page', function () {
    $supplier = supplierPaymentSupplier();
    $invoice = supplierPaymentInvoice($supplier, 'SP-UI-INV', '750.00', '2026-08-29');
    $payment = app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '250.00', [
        ['supplier_invoice_id' => $invoice->id, 'amount' => '250.00'],
    ]));

    $this->get(route('supplier-payments.index'))->assertOk()->assertSee('كل الحالات');
    $this->get(route('supplier-payments.create', ['supplier_id' => $supplier->id, 'supplier_invoice_id' => $invoice->id, 'amount' => 750]))->assertOk()->assertSee('توزيع تلقائي');
    $this->get(route('supplier-payments.show-payment', $payment))->assertOk()->assertSee('المسودة لا تؤثر')->assertSee('SP-UI-INV');
    $this->get(route('supplier-payments.edit', $payment))->assertOk()->assertSee('حفظ التعديلات');
    $this->getJson(route('supplier-payments.supplier-invoices', $supplier))->assertOk()->assertJsonPath('invoices.0.id', $invoice->id);
    $this->get(route('supplier-invoices.show', $invoice))->assertOk()->assertSee('ملخص السداد')->assertSee('سجل المدفوعات')->assertSee('تسجيل دفعة');
});

it('does not delete a confirmed supplier payment before cancellation', function () {
    $supplier = supplierPaymentSupplier();
    $invoice = supplierPaymentInvoice($supplier, 'SP-DELETE', '100.00', '2026-08-30');
    $payment = app(CreateSupplierPaymentAction::class)->handle(supplierPaymentData($supplier, '100.00', [
        ['supplier_invoice_id' => $invoice->id, 'amount' => '100.00'],
    ]));
    app(ConfirmSupplierPaymentAction::class)->handle($payment);

    expect(fn () => app(DeleteSupplierPaymentAction::class)->handle($payment))->toThrow(ValidationException::class)
        ->and(SupplierPayment::find($payment->id))->not->toBeNull();
});
