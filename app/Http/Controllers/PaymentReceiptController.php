<?php

namespace App\Http\Controllers;

use App\Actions\PaymentReceipt\CancelPaymentReceiptAction;
use App\Actions\PaymentReceipt\ConfirmPaymentReceiptAction;
use App\Actions\PaymentReceipt\CreatePaymentReceiptAction;
use App\Actions\PaymentReceipt\DeletePaymentReceiptAction;
use App\Actions\PaymentReceipt\UpdatePaymentReceiptAction;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentReceiptStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Http\Requests\PaymentReceipt\StorePaymentReceiptRequest;
use App\Http\Requests\PaymentReceipt\UpdatePaymentReceiptRequest;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Services\CustomerLedgerService;
use App\Services\Printing\DocumentPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $receipts = PaymentReceipt::query()
            ->with([
                'customer',
                'invoice',
            ])
            ->search($request->input('search'))
            ->filterStatus($request->input('status'))
            ->filterCustomer($request->input('customer_id'))
            ->filterDateFrom($request->input('date_from'))
            ->filterDateTo($request->input('date_to'))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
            ]);

        return view(
            'payment-receipts.index',
            compact(
                'receipts',
                'customers'
            )
        );
    }

    /**
     * Create payment receipt.
     */
    public function create(): View
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
            ]);

        return view(
            'payment-receipts.create',
            compact('customers')
        );
    }

    /**
     * Get unpaid sales invoices for customer.
     */
    public function customerInvoices(
        Customer $customer
    ): JsonResponse {
        $invoices = SalesInvoice::query()
            ->where('customer_id', $customer->id)
            ->where(
                'status',
                SalesInvoiceStatus::CONFIRMED
            )
            ->withSum([
                'paymentReceipts as paid_amount' => function ($query) {
                    $query->where(
                        'status',
                        PaymentReceiptStatus::CONFIRMED
                    );
                },
            ], 'amount')
            ->withSum([
                'salesReturns as returned_amount' => fn ($query) => $query
                    ->where('status', SalesReturnStatus::CONFIRMED),
            ], 'total_amount')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $invoices = $invoices
            ->map(function (SalesInvoice $invoice) {

                $total = (float) $invoice->total_amount;

                $paid = (float) ($invoice->paid_amount ?? 0);

                $returned = (float) ($invoice->returned_amount ?? 0);
                $remaining = max(0, $total - $returned - $paid);

                return [
                    'id' => $invoice->id,

                    'invoice_number' => $invoice->invoice_number,

                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),

                    'total_amount' => round($total, 2),

                    'paid_amount' => round($paid, 2),

                    'returned_amount' => round($returned, 2),

                    'remaining_amount' => round($remaining, 2),
                ];
            })
            ->filter(
                fn (array $invoice) => $invoice['remaining_amount'] > 0
            )
            ->values();

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    /**
     * Store payment receipt.
     */
    public function store(
        StorePaymentReceiptRequest $request,
        CreatePaymentReceiptAction $action,
    ): RedirectResponse {
        $receipt = $action->handle(
            $request->validated()
        );

        return redirect()
            ->route(
                'payment-receipts.show',
                $receipt
            )
            ->with(
                'success',
                'تم إنشاء سند القبض بنجاح.'
            );
    }

    /**
     * Show payment receipt.
     */
    public function show(
        PaymentReceipt $paymentReceipt
    ): View {
        $paymentReceipt->load([
            'customer',
            'invoice',
            'creator',
        ]);

        $ledgerEntry = CustomerLedgerEntry::query()
            ->where(
                'reference_type',
                LedgerReferenceType::PAYMENT_RECEIPT
            )
            ->where(
                'reference_id',
                $paymentReceipt->id
            )
            ->first();

        return view(
            'payment-receipts.show',
            compact(
                'paymentReceipt',
                'ledgerEntry'
            )
        );
    }

    /**
     * Print payment receipt.
     */
    public function print(
        PaymentReceipt $paymentReceipt,
        DocumentPrinter $printer,
        CustomerLedgerService $ledgerService,
    ): View {
        $paymentReceipt->load([
            'customer',
            'invoice',
            'creator',
        ]);

        $previousPaid = PaymentReceipt::query()
            ->where('sales_invoice_id', $paymentReceipt->sales_invoice_id)
            ->where('status', PaymentReceiptStatus::CONFIRMED)
            ->whereKeyNot($paymentReceipt->id)
            ->sum('amount');

        return $printer->render($paymentReceipt, 'payment_receipt', [
            'previousPaid' => (float) $previousPaid,
            'customerBalance' => (float) $ledgerService->getBalance($paymentReceipt->customer_id),
        ]);
    }

    /**
     * Edit payment receipt.
     */
    public function edit(
        PaymentReceipt $paymentReceipt
    ): View|RedirectResponse {
        if (
            $paymentReceipt->status !==
            PaymentReceiptStatus::DRAFT
        ) {
            return redirect()
                ->route(
                    'payment-receipts.show',
                    $paymentReceipt
                )
                ->with(
                    'error',
                    'لا يمكن تعديل إلا السندات المسودة.'
                );
        }

        $customers = Customer::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
            ]);

        return view(
            'payment-receipts.edit',
            compact(
                'paymentReceipt',
                'customers'
            )
        );
    }

    /**
     * Update payment receipt.
     */
    public function update(
        UpdatePaymentReceiptRequest $request,
        PaymentReceipt $paymentReceipt,
        UpdatePaymentReceiptAction $action,
    ): RedirectResponse {
        $action->handle(
            $paymentReceipt,
            $request->validated()
        );

        return redirect()
            ->route(
                'payment-receipts.show',
                $paymentReceipt
            )
            ->with(
                'success',
                'تم تحديث سند القبض بنجاح.'
            );
    }

    /**
     * Delete payment receipt.
     */
    public function destroy(
        PaymentReceipt $paymentReceipt,
        DeletePaymentReceiptAction $action,
    ): RedirectResponse {
        try {

            $action->handle(
                $paymentReceipt
            );

        } catch (ValidationException $e) {

            return redirect()
                ->route(
                    'payment-receipts.show',
                    $paymentReceipt
                )
                ->with(
                    'error',
                    $e->validator
                        ->errors()
                        ->first()
                );
        }

        return redirect()
            ->route(
                'payment-receipts.index'
            )
            ->with(
                'success',
                'تم حذف سند القبض بنجاح.'
            );
    }

    /**
     * Confirm payment receipt.
     */
    public function confirm(
        PaymentReceipt $paymentReceipt,
        ConfirmPaymentReceiptAction $action,
    ): RedirectResponse {
        try {

            $action->handle(
                $paymentReceipt
            );

        } catch (ValidationException $e) {

            return redirect()
                ->route(
                    'payment-receipts.show',
                    $paymentReceipt
                )
                ->with(
                    'error',
                    $e->validator
                        ->errors()
                        ->first()
                );
        }

        return redirect()
            ->route(
                'payment-receipts.show',
                $paymentReceipt
            )
            ->with(
                'success',
                'تم تأكيد سند القبض بنجاح.'
            );
    }

    /**
     * Cancel payment receipt.
     */
    public function cancel(
        PaymentReceipt $paymentReceipt,
        CancelPaymentReceiptAction $action,
    ): RedirectResponse {
        try {

            $action->handle(
                $paymentReceipt
            );

        } catch (ValidationException $e) {

            return redirect()
                ->route(
                    'payment-receipts.show',
                    $paymentReceipt
                )
                ->with(
                    'error',
                    $e->validator
                        ->errors()
                        ->first()
                );
        }

        return redirect()
            ->route(
                'payment-receipts.show',
                $paymentReceipt
            )
            ->with(
                'success',
                'تم إلغاء سند القبض بنجاح.'
            );
    }
}
