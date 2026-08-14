@extends('layouts.dashboard')

@section('title', 'Record Supplier Payment')

@section('content')

<div class="page-header">
    <h1 class="h3 mb-1">Record Supplier Payment</h1>
    <p class="text-muted mb-0">
        Record an invoice payment or supplier advance.
    </p>
</div>

<div class="card">
    <div class="card-body">

        <form method="POST" action="{{ route('supplier-payments.store') }}">
            @csrf

            <div class="row g-3">

                {{-- Payment Type --}}
                <div class="col-md-6">
                    <label class="form-label">Payment Type *</label>

                    <select
                        name="payment_type"
                        id="payment_type"
                        class="form-select"
                        required
                    >
                        <option value="invoice">
                            Invoice Payment
                        </option>

                        <option value="advance">
                            Advance Payment
                        </option>
                    </select>
                </div>

                {{-- Supplier --}}
                <div class="col-md-6">
                    <label class="form-label">
                        Supplier *
                    </label>

                    <select
                        name="supplier_id"
                        id="supplier"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Select supplier
                        </option>

                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

               {{-- Purchase Order --}}
<div
    class="col-md-6"
    id="purchase-order-wrapper"
>

    <label class="form-label">
        Purchase Order
    </label>

    <select
        name="purchase_order_id"
        id="purchase_order"
        class="form-select"
    >
        <option value="">
            Not linked
        </option>

        @foreach ($orders as $order)
            <option
                value="{{ $order->id }}"
                data-supplier="{{ $order->supplier_id }}"
            >
                {{ $order->purchase_order_number }}
                —
                {{ $order->supplier->name }}
                —
                {{ number_format($order->total, 2) }}
            </option>
        @endforeach
    </select>

    <small class="text-muted">
        Required for advance payments.
    </small>

</div>

                {{-- Supplier Invoice --}}
                <div
                    class="col-md-6"
                    id="invoice-wrapper"
                >

                    <label class="form-label">
                        Supplier Invoice *
                    </label>

                    <select
                        name="supplier_invoice_id"
                        id="supplier_invoice"
                        class="form-select"
                    >
                        <option value="">
                            Select invoice
                        </option>

                        @foreach ($invoices as $invoice)
                            <option
                                value="{{ $invoice->id }}"
                                data-supplier="{{ $invoice->supplier_id }}"
                                data-total="{{ $invoice->total }}"
                                data-remaining="{{ $invoice->remaining_amount }}"
                            >
                                {{ $invoice->invoice_number }}
                                —
                                {{ $invoice->supplier->name }}
                                —
                                Remaining:
                                {{ number_format($invoice->remaining_amount, 2) }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Invoice Summary --}}
                    <div
                        id="invoice-summary"
                        class="mt-3 p-3 border rounded bg-light"
                        style="display: none;"
                    >

                        <div class="row g-2">

                            <div class="col-md-4">
                                <small class="text-muted d-block">
                                    Invoice Total
                                </small>

                                <strong id="invoice-total">
                                    0.00
                                </strong>
                            </div>

                            <div class="col-md-4">
                                <small class="text-muted d-block">
                                    Already Paid
                                </small>

                                <strong id="invoice-paid">
                                    0.00
                                </strong>
                            </div>

                            <div class="col-md-4">
                                <small class="text-muted d-block">
                                    Remaining
                                </small>

                                <strong
                                    id="invoice-remaining"
                                    class="text-danger"
                                >
                                    0.00
                                </strong>
                            </div>

                        </div>

                    </div>

                </div>

                {{-- Payment Date --}}
                <div class="col-md-3">

                    <label class="form-label">
                        Payment Date *
                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        value="{{ now()->toDateString() }}"
                        class="form-control"
                        required
                    >

                </div>

                {{-- Amount --}}
                <div class="col-md-3">

                    <label class="form-label">
                        Amount *
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        min="0.01"
                        step="0.01"
                        class="form-control"
                        required
                    >

                    <small
                        id="amount-help"
                        class="text-muted"
                    >
                        Enter payment amount.
                    </small>

                </div>

                {{-- Method --}}
                <div class="col-md-6">

                    <label class="form-label">
                        Method *
                    </label>

                    <select
                        name="payment_method"
                        class="form-select"
                        required
                    >
                        <option value="cash">
                            Cash
                        </option>

                        <option value="bank_transfer">
                            Bank Transfer
                        </option>

                        <option value="cheque">
                            Cheque
                        </option>
                    </select>

                </div>

                {{-- Reference --}}
                <div class="col-md-6">

                    <label class="form-label">
                        Reference
                    </label>

                    <input
                        name="reference"
                        class="form-control"
                    >

                </div>

                {{-- Notes --}}
                <div class="col-12">

                    <label class="form-label">
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"
                    ></textarea>

                </div>

            </div>

            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Record Payment
                </button>

                <a
                    href="{{ route('supplier-payments.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>
</div>

@endsection


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const paymentType =
        document.getElementById('payment_type');
const purchaseOrderWrapper =
    document.getElementById('purchase-order-wrapper');
    const supplier =
        document.getElementById('supplier');

    const invoiceWrapper =
        document.getElementById('invoice-wrapper');

    const invoice =
        document.getElementById('supplier_invoice');

    const purchaseOrder =
        document.getElementById('purchase_order');

    const invoiceSummary =
        document.getElementById('invoice-summary');

    const invoiceTotal =
        document.getElementById('invoice-total');

    const invoicePaid =
        document.getElementById('invoice-paid');

    const invoiceRemaining =
        document.getElementById('invoice-remaining');

    const amount =
        document.getElementById('amount');

    const amountHelp =
        document.getElementById('amount-help');


    /*
    |--------------------------------------------------------------------------
    | Filter Options By Supplier
    |--------------------------------------------------------------------------
    */

    function filterOptions(select, supplierId) {

        const options =
            select.querySelectorAll('option');

        options.forEach(option => {

            if (option.value === '') {

                option.hidden = false;

                return;
            }

            const optionSupplier =
                option.dataset.supplier;

            option.hidden =
                supplierId !== '' &&
                optionSupplier !== supplierId;
        });

    }


    /*
    |--------------------------------------------------------------------------
    | Reset Invoice Information
    |--------------------------------------------------------------------------
    */

    function resetInvoiceInfo() {

        invoiceSummary.style.display = 'none';

        invoiceTotal.textContent = '0.00';

        invoicePaid.textContent = '0.00';

        invoiceRemaining.textContent = '0.00';

        amount.value = '';

        amount.removeAttribute('max');

        amountHelp.textContent =
            'Enter payment amount.';

    }


    /*
    |--------------------------------------------------------------------------
    | Update Invoice Information
    |--------------------------------------------------------------------------
    */

    function updateInvoiceInfo() {

        const selected =
            invoice.options[invoice.selectedIndex];

        if (
            !selected ||
            !selected.value
        ) {

            resetInvoiceInfo();

            return;
        }

        const total =
            parseFloat(selected.dataset.total || 0);

        const remaining =
            parseFloat(selected.dataset.remaining || 0);

        const paid =
            Math.max(total - remaining, 0);


        /*
        |--------------------------------------------------------------------------
        | Display Invoice Summary
        |--------------------------------------------------------------------------
        */

        invoiceSummary.style.display = '';

        invoiceTotal.textContent =
            total.toFixed(2);

        invoicePaid.textContent =
            paid.toFixed(2);

        invoiceRemaining.textContent =
            remaining.toFixed(2);


        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        amount.value =
            remaining.toFixed(2);

        amount.max =
            remaining.toFixed(2);

        amountHelp.textContent =
            `Maximum payment: ${remaining.toFixed(2)}`;


        /*
        |--------------------------------------------------------------------------
        | Disable Payment If Already Paid
        |--------------------------------------------------------------------------
        */

        if (remaining <= 0) {

            amount.value = '';

            amount.disabled = true;

            amountHelp.textContent =
                'This invoice is already fully paid.';

            invoiceRemaining.classList
                .remove('text-danger');

            invoiceRemaining.classList
                .add('text-success');

        } else {

            amount.disabled = false;

            invoiceRemaining.classList
                .remove('text-success');

            invoiceRemaining.classList
                .add('text-danger');
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Payment Type
    |--------------------------------------------------------------------------
    */
function updatePaymentType() {

    const isAdvance =
        paymentType.value === 'advance';

    if (isAdvance) {

        /*
        |--------------------------------------------------------------------------
        | Advance Payment
        |--------------------------------------------------------------------------
        */

        invoiceWrapper.style.display = 'none';

        purchaseOrderWrapper.style.display = '';

        invoice.value = '';

        invoice.removeAttribute('required');

        purchaseOrder.setAttribute(
            'required',
            'required'
        );

        resetInvoiceInfo();

    } else {

        /*
        |--------------------------------------------------------------------------
        | Invoice Payment
        |--------------------------------------------------------------------------
        */

        invoiceWrapper.style.display = '';

        purchaseOrderWrapper.style.display = 'none';

        invoice.setAttribute(
            'required',
            'required'
        );

        purchaseOrder.removeAttribute(
            'required'
        );

        /*
        | Do not send Purchase Order
        | when paying an invoice.
        */

        purchaseOrder.value = '';

    }

}


    /*
    |--------------------------------------------------------------------------
    | Payment Type Change
    |--------------------------------------------------------------------------
    */

    paymentType.addEventListener(
        'change',
        updatePaymentType
    );


    /*
    |--------------------------------------------------------------------------
    | Supplier Change
    |--------------------------------------------------------------------------
    */

    supplier.addEventListener(
        'change',
        function () {

            const supplierId =
                this.value;

            filterOptions(
                invoice,
                supplierId
            );

            filterOptions(
                purchaseOrder,
                supplierId
            );


            /*
            |--------------------------------------------------------------------------
            | Reset Current Selections
            |--------------------------------------------------------------------------
            */

            invoice.value = '';

            purchaseOrder.value = '';

            resetInvoiceInfo();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Invoice Change
    |--------------------------------------------------------------------------
    */

    invoice.addEventListener(
        'change',
        updateInvoiceInfo
    );


    /*
    |--------------------------------------------------------------------------
    | Prevent Payment Greater Than Remaining
    |--------------------------------------------------------------------------
    */

    amount.addEventListener(
        'input',
        function () {

            if (!invoice.value) {
                return;
            }

            const selected =
                invoice.options[
                    invoice.selectedIndex
                ];

            if (!selected) {
                return;
            }

            const remaining =
                parseFloat(
                    selected.dataset.remaining || 0
                );

            const entered =
                parseFloat(this.value || 0);

            if (entered > remaining) {

                this.setCustomValidity(
                    `Payment cannot exceed remaining amount of ${remaining.toFixed(2)}.`
                );

            } else {

                this.setCustomValidity('');
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initialize
    |--------------------------------------------------------------------------
    */

    updatePaymentType();

});

</script>

@endpush