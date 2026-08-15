<?php

namespace App\Http\Controllers;

use App\Actions\PaymentReceipt\CancelPaymentReceiptAction;
use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\PaymentReceipt\DeletePaymentReceiptAction;
use App\Actions\PaymentReceipt\UpdatePaymentReceiptAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Http\Requests\PaymentReceipt\StorePaymentReceiptRequest;
use App\Http\Requests\PaymentReceipt\UpdatePaymentReceiptRequest;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\Printing\DocumentPrinter;

class PaymentReceiptController extends Controller
{
    public function print(
        PaymentReceipt $paymentReceipt,
        DocumentPrinter $printer
    ): View {
        $paymentReceipt->load([
            'customer',
            'creator',
        ]);

        return $printer->render(
            $paymentReceipt,
            'payment_receipt'
        );
    }
    public function index(Request $request): View
    {
        $receipts = PaymentReceipt::query()
            ->with('customer')
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterCustomer($request->input('customer_id'))
            ->filterDateFrom($request->input('date_from'))
            ->filterDateTo($request->input('date_to'))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('payment-receipts.index', compact('receipts', 'customers'));
    }

    public function create(): View
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('payment-receipts.create', compact('customers'));
    }

    public function store(
        StorePaymentReceiptRequest $request,
        CreatePaymentReceiptAction $action,
    ): RedirectResponse {
        $receipt = $action->handle($request->validated());

        return redirect()
            ->route('payment-receipts.show', $receipt)
            ->with('success', 'Payment receipt created successfully.');
    }

    public function show(PaymentReceipt $paymentReceipt): View
    {
        $paymentReceipt->load(['customer', 'creator']);

        $ledgerEntry = CustomerLedgerEntry::query()
            ->where('reference_type', LedgerReferenceType::PAYMENT_RECEIPT)
            ->where('reference_id', $paymentReceipt->id)
            ->first();

        return view('payment-receipts.show', compact('paymentReceipt', 'ledgerEntry'));
    }

    public function edit(PaymentReceipt $paymentReceipt): View|RedirectResponse
    {
        if ($paymentReceipt->status !== PaymentReceiptStatus::DRAFT) {
            return redirect()
                ->route('payment-receipts.show', $paymentReceipt)
                ->with('error', 'Only draft receipts can be edited.');
        }

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('payment-receipts.edit', compact('paymentReceipt', 'customers'));
    }

    public function update(
        UpdatePaymentReceiptRequest $request,
        PaymentReceipt $paymentReceipt,
        UpdatePaymentReceiptAction $action,
    ): RedirectResponse {
        $action->handle($paymentReceipt, $request->validated());

        return redirect()
            ->route('payment-receipts.show', $paymentReceipt)
            ->with('success', 'Payment receipt updated successfully.');
    }

    public function destroy(
        PaymentReceipt $paymentReceipt,
        DeletePaymentReceiptAction $action,
    ): RedirectResponse {
        try {
            $action->handle($paymentReceipt);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('payment-receipts.show', $paymentReceipt)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('payment-receipts.index')
            ->with('success', 'Payment receipt deleted successfully.');
    }

    public function confirm(
        PaymentReceipt $paymentReceipt,
        ConfirmPaymentReceiptAction $action,
    ): RedirectResponse {
        try {
            $action->handle($paymentReceipt);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('payment-receipts.show', $paymentReceipt)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('payment-receipts.show', $paymentReceipt)
            ->with('success', 'Payment receipt confirmed successfully.');
    }

    public function cancel(
        PaymentReceipt $paymentReceipt,
        CancelPaymentReceiptAction $action,
    ): RedirectResponse {
        try {
            $action->handle($paymentReceipt);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('payment-receipts.show', $paymentReceipt)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('payment-receipts.show', $paymentReceipt)
            ->with('success', 'Payment receipt cancelled successfully.');
    }
}
