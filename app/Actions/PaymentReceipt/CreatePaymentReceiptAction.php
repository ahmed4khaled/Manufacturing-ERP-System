<?php

namespace App\Actions\PaymentReceipt;

use App\Enums\PaymentReceiptStatus;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentReceiptAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): PaymentReceipt
    {
        return DB::transaction(function () use ($data) {

            $data['status'] =
                PaymentReceiptStatus::DRAFT;

            $data['created_by'] =
                auth()->id();

            /*
            |--------------------------------------------------------------------------
            | Invoice Payment
            |--------------------------------------------------------------------------
            */

            if (($data['payment_type'] ?? 'invoice') === 'invoice') {

                $invoice = SalesInvoice::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $data['sales_invoice_id']
                    );

                /*
                |--------------------------------------------------------------------------
                | Make Sure Invoice Belongs To Customer
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $invoice->customer_id !==
                    (int) $data['customer_id']
                ) {
                    throw ValidationException::withMessages([
                        'sales_invoice_id' =>
                            'The selected invoice does not belong to the selected customer.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Already Paid
                |--------------------------------------------------------------------------
                */

                $paidAmount = PaymentReceipt::query()
                    ->where(
                        'sales_invoice_id',
                        $invoice->id
                    )
                    ->where(
                        'status',
                        PaymentReceiptStatus::CONFIRMED
                    )
                    ->sum('amount');

                $remainingAmount = max(
                    0,
                    (float) $invoice->total_amount -
                    (float) $paidAmount
                );

                /*
                |--------------------------------------------------------------------------
                | Prevent Overpayment
                |--------------------------------------------------------------------------
                */

                if (
                    (float) $data['amount'] >
                    $remainingAmount
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Payment amount cannot exceed the remaining invoice balance. Remaining: '
                            . number_format(
                                $remainingAmount,
                                2
                            )
                            . ' EGP.',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Receipt
            |--------------------------------------------------------------------------
            */

            $receipt =
                PaymentReceipt::create($data);

            /*
            |--------------------------------------------------------------------------
            | Generate Receipt Number
            |--------------------------------------------------------------------------
            */

            $receipt->update([
                'receipt_number' =>
                    $this->codeGenerator->next('RCPT'),
            ]);

            return $receipt->fresh([
                'customer',
                'invoice',
                'creator',
            ]);
        });
    }
}