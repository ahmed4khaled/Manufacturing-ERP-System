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
use App\Services\CustomerLedgerService;
use App\Services\PaymentReceiptAllocationService;
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
        Request $request,
        Customer $customer,
        PaymentReceiptAllocationService $allocationService,
    ): JsonResponse {
        $excludeReceiptId = null;
        if ($request->filled('exclude_receipt_id')) {
            $excludeReceiptId = PaymentReceipt::query()
                ->whereKey($request->integer('exclude_receipt_id'))
                ->where('customer_id', $customer->id)
                ->where('status', PaymentReceiptStatus::DRAFT)
                ->value('id');
        }

        return response()->json([
            'invoices' => $allocationService->availableInvoices($customer->id, $excludeReceiptId),
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
        PaymentReceipt $paymentReceipt,
        PaymentReceiptAllocationService $allocationService,
    ): View {
        $paymentReceipt->load([
            'customer',
            'invoice',
            'allocations.salesInvoice',
            'creator',
        ]);
        $allocationBreakdown = $allocationService->breakdown($paymentReceipt);

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
                'ledgerEntry',
                'allocationBreakdown',
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
        PaymentReceiptAllocationService $allocationService,
    ): View {
        $paymentReceipt->load([
            'customer',
            'invoice',
            'allocations.salesInvoice',
            'creator',
        ]);

        $allocationBreakdown = $allocationService->breakdown($paymentReceipt);

        return $printer->render($paymentReceipt, 'payment_receipt', [
            'allocationBreakdown' => $allocationBreakdown,
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
        $paymentReceipt->load('allocations.salesInvoice');

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
