@extends('layouts.dashboard')

@section('title', 'إنشاء فاتورة مورد')

@section('content')

<div class="page-header">
    <h1 class="h3 mb-1">إنشاء فاتورة مورد</h1>
    <p class="text-muted mb-0">
        سجّل فاتورة مستحقة لمورد.
    </p>
</div>

<form method="POST"
      action="{{ route('supplier-invoices.store') }}"
      id="invoiceForm">

    @csrf

    <div class="card">
        <div class="card-body">

            <div class="row g-3">

                {{-- المورد --}}
                <div class="col-md-6">
                    <label class="form-label">المورد *</label>

                    <select
                        name="supplier_id"
                        id="supplier"
                        class="form-select"
                        required
                    >
                        <option value="">اختر المورد</option>

                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">
                                {{ $supplier->name }}
                            </option>
                        @endforeach

                    </select>
                </div>


                {{-- Purchase Order --}}
                <div class="col-md-6">

                    <label class="form-label">
                        أمر الشراء
                    </label>

                    <select
                        name="purchase_order_id"
                        id="purchase_order"
                        class="form-select"
                    >

                        <option value="">
                            غير مرتبط
                        </option>

                        @foreach ($orders as $order)

                            <option
                                value="{{ $order->id }}"
                                data-supplier="{{ $order->supplier_id }}"
                            >
                                {{ $order->purchase_order_number }}
                                — {{ $order->supplier->name }}
                                — {{ $order->status }}
                                — {{ $order->order_date }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Goods Receipt --}}
                <div class="col-md-6">

                    <label class="form-label">
                        إذن الاستلام
                    </label>

                    <select
                        name="goods_receipt_id"
                        id="goods_receipt"
                        class="form-select"
                    >

                        <option value="">
                            غير مرتبط
                        </option>

                        @foreach ($receipts as $receipt)

                            <option
                                value="{{ $receipt->id }}"
                                data-supplier="{{ $receipt->supplier_id }}"
                                data-purchase-order="{{ $receipt->purchase_order_id }}"
                            >
                                {{ $receipt->receipt_number }}
                                — {{ $receipt->supplier->name }}
                                — {{ $receipt->received_at }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Invoice Date --}}
                <div class="col-md-3">

                    <label class="form-label">
                        تاريخ الفاتورة *
                    </label>

                    <input
                        type="date"
                        name="invoice_date"
                        value="{{ now()->toDateString() }}"
                        class="form-control"
                        required
                    >

                </div>


                {{-- Due Date --}}
                <div class="col-md-3">

                    <label class="form-label">
                        تاريخ الاستحقاق
                    </label>

                    <input
                        type="date"
                        name="due_date"
                        class="form-control"
                    >

                </div>


                {{-- ===================================================== --}}
                {{-- Advance Payment --}}
                {{-- ===================================================== --}}

                <div
                    class="col-12"
                    id="advancePaymentWrapper"
                    style="display:none;"
                >

                    <div class="alert alert-warning mb-0">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <strong>
                                    دفعة مقدمة
                                </strong>

                                <div class="small text-muted">
                                    المبلغ المدفوع سابقًا لأمر الشراء.
                                </div>

                            </div>

                            <div class="text-end">

                                <strong
                                    id="advancePaymentAmount"
                                    class="fs-5"
                                >
                                    0.00
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- Receipt Items --}}
                <div class="col-12">

                    <div
                        id="receiptItemsWrapper"
                        class="mt-4"
                        style="display:none;"
                    >

                        <h5 class="mb-3">
                            بنود إذن الاستلام
                        </h5>

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead>

                                    <tr>
                                        <th>الخامة</th>
                                        <th>المطلوب</th>
                                        <th>المستلم</th>
                                        <th>المقبول</th>
                                        <th>المرفوض</th>
                                        <th>تكلفة الوحدة</th>
                                        <th>إجمالي البند</th>
                                    </tr>

                                </thead>

                                <tbody id="receiptItems"></tbody>

                                <tfoot>

                                    <tr>

                                        <th
                                            colspan="6"
                                            class="text-end"
                                        >
                                            الإجمالي الفرعي
                                        </th>

                                        <th>

                                            <span id="calculatedSubtotal">
                                                0.00
                                            </span>

                                        </th>

                                    </tr>

                                </tfoot>

                            </table>

                        </div>

                    </div>

                </div>


                {{-- Subtotal --}}
                <div class="col-md-4">

                    <label class="form-label">
                        الإجمالي الفرعي *
                    </label>

                    <input
                        type="number"
                        name="subtotal"
                        id="subtotal"
                        min="0"
                        step=".01"
                        value="0.00"
                        class="form-control"
                        required
                    >

                    <small class="text-muted">
                        يُحسب تلقائيًا من الكميات المقبولة.
                    </small>

                </div>


                {{-- Discount --}}
                <div class="col-md-4">

                    <label class="form-label">
                        الخصم
                    </label>

                    <input
                        type="number"
                        name="discount"
                        id="discount"
                        min="0"
                        step=".01"
                        value="0.00"
                        class="form-control"
                    >

                </div>


                {{-- Tax --}}
                <div class="col-md-4">

                    <label class="form-label">
                        الضريبة
                    </label>

                    <input
                        type="number"
                        name="tax"
                        id="tax"
                        min="0"
                        step=".01"
                        value="0.00"
                        class="form-control"
                    >

                </div>


                {{-- Invoice Total --}}
                <div class="col-md-4">

                    <label class="form-label">
                        إجمالي الفاتورة
                    </label>

                    <input
                        type="text"
                        id="total"
                        class="form-control fw-bold"
                        value="0.00"
                        readonly
                    >

                </div>


                {{-- Advance --}}
                <div class="col-md-4">

                    <label class="form-label">
                        المقدم المدفوع
                    </label>

                    <input
                        type="text"
                        id="advanceDisplay"
                        class="form-control fw-bold"
                        value="0.00"
                        readonly
                    >

                </div>


                {{-- Remaining --}}
                <div class="col-md-4">

                    <label class="form-label">
                        المبلغ المتبقي
                    </label>

                    <input
                        type="text"
                        id="remainingDisplay"
                        class="form-control fw-bold"
                        value="0.00"
                        readonly
                    >

                </div>


                {{-- Hidden Advance Amount --}}
                <input
                    type="hidden"
                    name="advance_amount"
                    id="advance_amount"
                    value="0.00"
                >


                {{-- Notes --}}
                <div class="col-12">

                    <label class="form-label">
                        ملاحظات
                    </label>

                    <textarea
                        name="notes"
                        rows="3"
                        class="form-control"
                    ></textarea>

                </div>

            </div>


            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    إنشاء فاتورة
                </button>

                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('supplier-invoices.index') }}"
                >
                    إلغاء
                </a>

            </div>

        </div>
    </div>

</form>


{{-- ========================================================= --}}
{{-- Prepare Receipt Data --}}
{{-- ========================================================= --}}

@php

$receiptData = $receipts->mapWithKeys(function ($receipt) {

    return [

        $receipt->id => [

            'id' =>
                $receipt->id,

            'receipt_number' =>
                $receipt->receipt_number,

            'supplier_id' =>
                $receipt->supplier_id,

            'purchase_order_id' =>
                $receipt->purchase_order_id,

            'items' => $receipt->items->map(function ($item) {

                return [

                    'id' =>
                        $item->id,

                    'material_id' =>
                        $item->material_id,

                    'material_code' =>
                        $item->material?->code,

                    'material_name' =>
                        $item->material?->name,

                    'ordered_quantity' =>
                        (float) $item->ordered_quantity,

                    'received_quantity' =>
                        (float) $item->received_quantity,

                    'accepted_quantity' =>
                        (float) $item->accepted_quantity,

                    'rejected_quantity' =>
                        (float) $item->rejected_quantity,

                    'unit_cost' =>
                        (float) $item->unit_cost,

                ];

            })->values()->all(),

        ],

    ];

})->all();

@endphp


{{-- ========================================================= --}}
{{-- Prepare Advance Payment Data --}}
{{-- ========================================================= --}}

@php

$advancePaymentData = [];

foreach ($advancePayments ?? [] as $payment) {

    $advancePaymentData[$payment->purchase_order_id] =
        (float) $payment->total_advance;

}

@endphp


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const supplierSelect =
        document.getElementById('supplier');

    const purchaseOrderSelect =
        document.getElementById('purchase_order');

    const goodsReceiptSelect =
        document.getElementById('goods_receipt');

    const receiptItems =
        document.getElementById('receiptItems');

    const receiptItemsWrapper =
        document.getElementById('receiptItemsWrapper');

    const subtotalInput =
        document.getElementById('subtotal');

    const calculatedSubtotal =
        document.getElementById('calculatedSubtotal');

    const discountInput =
        document.getElementById('discount');

    const taxInput =
        document.getElementById('tax');

    const totalInput =
        document.getElementById('total');

    const advancePaymentWrapper =
        document.getElementById('advancePaymentWrapper');

    const advancePaymentAmount =
        document.getElementById('advancePaymentAmount');

    const advanceDisplay =
        document.getElementById('advanceDisplay');

    const advanceAmountInput =
        document.getElementById('advance_amount');

    const remainingDisplay =
        document.getElementById('remainingDisplay');


    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    const receiptData =
        @json($receiptData);

    const advancePaymentData =
        @json($advancePaymentData);


    /*
    |--------------------------------------------------------------------------
    | Advance Payment
    |--------------------------------------------------------------------------
    */

    function updateAdvancePayment() {

        const purchaseOrderId =
            purchaseOrderSelect.value;

        const advance =
            parseFloat(
                advancePaymentData[purchaseOrderId] ?? 0
            ) || 0;


        advancePaymentAmount.textContent =
            advance.toFixed(2);

        advanceDisplay.value =
            advance.toFixed(2);

        advanceAmountInput.value =
            advance.toFixed(2);


        if (advance > 0) {

            advancePaymentWrapper.style.display =
                'block';

        } else {

            advancePaymentWrapper.style.display =
                'none';

        }


        calculateTotals();

    }


    /*
    |--------------------------------------------------------------------------
    | Filter Purchase Orders
    |--------------------------------------------------------------------------
    */

    function filterPurchaseOrders(supplierId) {

        const options =
            purchaseOrderSelect.querySelectorAll('option');

        purchaseOrderSelect.value = '';

        options.forEach(function (option) {

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

        updateAdvancePayment();

    }


    /*
    |--------------------------------------------------------------------------
    | Filter Goods Receipts
    |--------------------------------------------------------------------------
    */

    function filterGoodsReceipts(
        supplierId,
        purchaseOrderId = ''
    ) {

        const options =
            goodsReceiptSelect.querySelectorAll('option');

        goodsReceiptSelect.value = '';

        options.forEach(function (option) {

            if (option.value === '') {

                option.hidden = false;

                return;
            }

            const optionSupplier =
                option.dataset.supplier;

            const optionPurchaseOrder =
                option.dataset.purchaseOrder;

            let show = true;


            if (
                supplierId !== '' &&
                optionSupplier !== supplierId
            ) {

                show = false;

            }


            if (
                purchaseOrderId !== '' &&
                optionPurchaseOrder !== purchaseOrderId
            ) {

                show = false;

            }


            option.hidden = !show;

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Render Receipt
    |--------------------------------------------------------------------------
    */

    function renderReceipt(receiptId) {

        receiptItems.innerHTML = '';

        receiptItemsWrapper.style.display =
            'none';


        if (!receiptId) {

            calculateTotals();

            return;
        }


        const receipt =
            receiptData[receiptId];


        if (!receipt || !receipt.items.length) {

            return;
        }


        let html = '';


        receipt.items.forEach(function (item, index) {

            html += `

                <tr>

                    <td>

                        <strong>
                            ${item.material_code ?? ''}
                        </strong>

                        <br>

                        <small>
                            ${item.material_name ?? ''}
                        </small>

                        <input
                            type="hidden"
                            name="items[${index}][goods_receipt_item_id]"
                            value="${item.id}"
                        >

                    </td>


                    <td>
                        ${item.ordered_quantity.toFixed(3)}
                    </td>


                    <td>
                        ${item.received_quantity.toFixed(3)}
                    </td>


                    <td>

                        <span class="badge bg-success">
                            ${item.accepted_quantity.toFixed(3)}
                        </span>

                    </td>


                    <td>

                        <span class="badge bg-danger">
                            ${item.rejected_quantity.toFixed(3)}
                        </span>

                    </td>


                    <td>

                        <input
                            type="number"
                            name="items[${index}][unit_cost]"
                            value="${item.unit_cost.toFixed(2)}"
                            min="0"
                            step=".01"
                            class="form-control unit-cost"
                            data-accepted="${item.accepted_quantity}"
                            required
                        >

                    </td>


                    <td>

                        <span class="line-total">
                            0.00
                        </span>

                    </td>

                </tr>

            `;

        });


        receiptItems.innerHTML =
            html;

        receiptItemsWrapper.style.display =
            'block';


        receiptItems
            .querySelectorAll('.unit-cost')
            .forEach(function (input) {

                input.addEventListener(
                    'input',
                    calculateTotals
                );

            });


        calculateTotals();

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Totals
    |--------------------------------------------------------------------------
    */
  function calculateTotals() {

    let subtotal = 0;

    const rows =
        receiptItems.querySelectorAll('tr');


    rows.forEach(function (row) {

        const costInput =
            row.querySelector('.unit-cost');

        const lineTotalElement =
            row.querySelector('.line-total');


        if (!costInput || !lineTotalElement) {
            return;
        }


        const accepted =
            parseFloat(
                costInput.dataset.accepted
            ) || 0;


        const unitCost =
            parseFloat(
                costInput.value
            ) || 0;


        const lineTotal =
            accepted * unitCost;


        subtotal +=
            lineTotal;


        // Line Total
        lineTotalElement.textContent =
            lineTotal.toLocaleString('ar-EG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

    });


    /*
    |--------------------------------------------------------------------------
    | Subtotal
    |--------------------------------------------------------------------------
    */

    subtotalInput.value =
        subtotal.toFixed(2);

    calculatedSubtotal.textContent =
        subtotal.toLocaleString('ar-EG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });


    /*
    |--------------------------------------------------------------------------
    | Discount & Tax
    |--------------------------------------------------------------------------
    */

    const discount =
        parseFloat(
            discountInput.value
        ) || 0;


    const tax =
        parseFloat(
            taxInput.value
        ) || 0;


    /*
    |--------------------------------------------------------------------------
    | Invoice Total
    |--------------------------------------------------------------------------
    */

    const total =
        Math.max(
            0,
            subtotal - discount + tax
        );


    totalInput.value =
        total.toLocaleString('ar-EG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });


    /*
    |--------------------------------------------------------------------------
    | Advance
    |--------------------------------------------------------------------------
    */

    const advance =
        parseFloat(
            advanceAmountInput.value
        ) || 0;


    /*
    |--------------------------------------------------------------------------
    | Remaining
    |--------------------------------------------------------------------------
    */

    const remaining =
        Math.max(
            0,
            total - advance
        );


    remainingDisplay.value =
        remaining.toLocaleString('ar-EG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

}

    /*
    |--------------------------------------------------------------------------
    | Goods Receipt Changed
    |--------------------------------------------------------------------------
    */

    goodsReceiptSelect.addEventListener(
        'change',
        function () {

            renderReceipt(
                this.value
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Discount / Tax Changed
    |--------------------------------------------------------------------------
    */

    discountInput.addEventListener(
        'input',
        calculateTotals
    );


    taxInput.addEventListener(
        'input',
        calculateTotals
    );


    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */

    calculateTotals();

});

</script>

@endpush

@endsection
