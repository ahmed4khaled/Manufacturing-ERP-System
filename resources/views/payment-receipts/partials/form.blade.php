@props([
    'paymentReceipt' => null,
    'customers' => [],
    'formAction' => '',
    'formMethod' => 'POST',
    'submitLabel' => 'حفظ السند',
])

<form method="POST" action="{{ $formAction }}" id="paymentReceiptForm">

    @csrf

    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif


    <div class="row g-4">

        {{-- =====================================================
             MAIN
        ====================================================== --}}

        <div class="col-lg-8">

            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        تفاصيل السند
                    </h5>

                </div>


                <div class="card-body">

                    <div class="row g-3">


                        {{-- =====================================================
                             CUSTOMER
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="customer_id"
                                class="form-label"
                            >
                                العميل
                                <span class="text-danger">*</span>
                            </label>


                            <select
                                name="customer_id"
                                id="customer_id"
                                class="form-select @error('customer_id') is-invalid @enderror"
                                required
                            >

                                <option value="">
                                    اختر العميل
                                </option>


                                @foreach ($customers as $customer)

                                    <option
                                        value="{{ $customer->id }}"
                                        @selected(
                                            old(
                                                'customer_id',
                                                $paymentReceipt?->customer_id
                                            ) == $customer->id
                                        )
                                    >

                                        {{ $customer->code }}
                                        —
                                        {{ $customer->name }}

                                    </option>

                                @endforeach

                            </select>


                            @error('customer_id')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             PAYMENT DATE
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="payment_date"
                                class="form-label"
                            >
                                تاريخ السداد
                                <span class="text-danger">*</span>
                            </label>


                            <input
                                type="date"
                                name="payment_date"
                                id="payment_date"
                                class="form-control @error('payment_date') is-invalid @enderror"
                                value="{{ old(
                                    'payment_date',
                                    $paymentReceipt?->payment_date?->format('Y-m-d')
                                    ?? now()->format('Y-m-d')
                                ) }}"
                                required
                            >


                            @error('payment_date')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             PAYMENT TYPE
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="payment_type"
                                class="form-label"
                            >
                                نوع السداد
                                <span class="text-danger">*</span>
                            </label>


                            <select
                                name="payment_type"
                                id="payment_type"
                                class="form-select @error('payment_type') is-invalid @enderror"
                                required
                            >

                                <option
                                    value="invoice"
                                    @selected(
                                        old(
                                            'payment_type',
                                            $paymentReceipt?->payment_type ?? 'invoice'
                                        ) === 'invoice'
                                    )
                                >
                                    سداد فاتورة
                                </option>


                                <option
                                    value="advance"
                                    @selected(
                                        old(
                                            'payment_type',
                                            $paymentReceipt?->payment_type
                                        ) === 'advance'
                                    )
                                >
                                    دفعة مقدمة
                                </option>

                            </select>


                            @error('payment_type')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             SALES INVOICE
                        ====================================================== --}}

                        <div
                            class="col-md-6"
                            id="invoiceWrapper"
                        >

                            <label
                                for="sales_invoice_id"
                                class="form-label"
                            >
                                فاتورة مبيعات
                                <span class="text-danger">*</span>
                            </label>


                            <select
                                name="sales_invoice_id"
                                id="sales_invoice_id"
                                class="form-select @error('sales_invoice_id') is-invalid @enderror"
                            >

                                <option value="">
                                    اختر الفاتورة
                                </option>

                            </select>


                            <div
                                id="invoiceLoading"
                                class="form-text d-none"
                            >
                                جارٍ تحميل الفواتير...
                            </div>


                            <div
                                id="invoiceEmpty"
                                class="form-text text-muted d-none"
                            >
                                لا توجد فواتير غير مسددة لهذا العميل.
                            </div>


                            @error('sales_invoice_id')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             INVOICE SUMMARY
                        ====================================================== --}}

                        <div
                            class="col-12 d-none"
                            id="invoiceSummaryWrapper"
                        >

                            <div class="card border bg-light">

                                <div class="card-body">

                                    <div class="row g-3">


                                        <div class="col-md-4">

                                            <div class="text-muted small">
                                                إجمالي الفاتورة
                                            </div>

                                            <div
                                                class="fw-bold"
                                                id="invoiceTotal"
                                            >
                                                0.00 ج.م
                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="text-muted small">
                                                المبلغ المدفوع
                                            </div>

                                            <div
                                                class="fw-bold text-success"
                                                id="invoicePaid"
                                            >
                                                0.00 ج.م
                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="text-muted small">
                                                المتبقي
                                            </div>

                                            <div
                                                class="fw-bold text-danger"
                                                id="invoiceRemaining"
                                            >
                                                0.00 ج.م
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        {{-- =====================================================
                             AMOUNT
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="amount"
                                class="form-label"
                            >
                                المبلغ (ج.م)
                                <span class="text-danger">*</span>
                            </label>


                            <input
                                type="number"
                                name="amount"
                                id="amount"
                                step="0.01"
                                min="0.01"
                                class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old(
                                    'amount',
                                    $paymentReceipt?->amount
                                ) }}"
                                required
                            >


                            <div
                                id="amountHelp"
                                class="form-text"
                            >
                                أدخل المبلغ المستلم من العميل.
                            </div>


                            @error('amount')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             PAYMENT STATUS
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label class="form-label">
                                حالة السداد
                            </label>


                            <div
                                class="form-control bg-light"
                                id="paymentStatus"
                            >
                                —
                            </div>

                        </div>


                        {{-- =====================================================
                             PAYMENT METHOD
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="payment_method"
                                class="form-label"
                            >
                                طريقة الدفع
                                <span class="text-danger">*</span>
                            </label>


                            <select
                                name="payment_method"
                                id="payment_method"
                                class="form-select @error('payment_method') is-invalid @enderror"
                                required
                            >

                                <option value="">
                                    اختر طريقة الدفع
                                </option>


                                @foreach (\App\Enums\PaymentMethod::cases() as $method)

                                    <option
                                        value="{{ $method->value }}"
                                        @selected(
                                            old(
                                                'payment_method',
                                                $paymentReceipt?->payment_method?->value
                                            ) === $method->value
                                        )
                                    >

                                        {{ $method->label() }}

                                    </option>

                                @endforeach

                            </select>


                            @error('payment_method')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             REFERENCE
                        ====================================================== --}}

                        <div class="col-md-6">

                            <label
                                for="reference_number"
                                class="form-label"
                            >
                                رقم المرجع
                            </label>


                            <input
                                type="text"
                                name="reference_number"
                                id="reference_number"
                                class="form-control @error('reference_number') is-invalid @enderror"
                                value="{{ old(
                                    'reference_number',
                                    $paymentReceipt?->reference_number
                                ) }}"
                                placeholder="رقم الشيك أو مرجع التحويل..."
                            >


                            @error('reference_number')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                        {{-- =====================================================
                             NOTES
                        ====================================================== --}}

                        <div class="col-12">

                            <label
                                for="notes"
                                class="form-label"
                            >
                                ملاحظات
                            </label>


                            <textarea
                                name="notes"
                                id="notes"
                                rows="3"
                                class="form-control @error('notes') is-invalid @enderror"
                            >{{ old(
                                'notes',
                                $paymentReceipt?->notes
                            ) }}</textarea>


                            @error('notes')

                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>

                            @enderror

                        </div>


                    </div>

                </div>

            </div>

        </div>


        {{-- =====================================================
             SIDE INFORMATION
        ====================================================== --}}

        <div class="col-lg-4">

            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        البيانات
                    </h5>

                </div>


                <div class="card-body">

                    @if ($paymentReceipt)

                        <div class="mb-3">

                            <label class="form-label text-muted small mb-1">
                                رقم السند
                            </label>

                            <p class="mb-0 fw-semibold">
                                {{ $paymentReceipt->receipt_number }}
                            </p>

                        </div>


                        <div class="mb-3">

                            <label class="form-label text-muted small mb-1">
                                الحالة
                            </label>

                            <p class="mb-0">

                                <span
                                    class="badge bg-{{ $paymentReceipt->status->badgeClass() }}"
                                >
                                    {{ $paymentReceipt->status->label() }}
                                </span>

                            </p>

                            <div class="form-text">

                                استخدم إجراءات التأكيد أو الإلغاء
                                من صفحة السند لتغيير حالته.

                            </div>

                        </div>

                    @else

                        <p class="text-muted small mb-0">

                            <i class="bi bi-info-circle me-1"></i>

                            سيُنشأ رقم السند تلقائيًا
                            (مثال: RCPT000001) عند الحفظ.

                        </p>

                    @endif


                </div>

            </div>


            {{-- =====================================================
                 ACTIONS
            ====================================================== --}}

            <div class="d-flex gap-2 mt-4">

                <button
                    type="submit"
                    class="btn btn-primary btn-lg"
                    id="submitButton"
                >

                    <i class="bi bi-check-lg me-1"></i>

                    {{ $submitLabel }}

                </button>


                <a
                    href="{{
                        $paymentReceipt
                            ? route('payment-receipts.show', $paymentReceipt)
                            : route('payment-receipts.index')
                    }}"
                    class="btn btn-outline-secondary btn-lg"
                >
                    إلغاء
                </a>

            </div>

        </div>

    </div>

</form>


{{-- =========================================================
     JAVASCRIPT
========================================================= --}}

<script>

document.addEventListener('DOMContentLoaded', function () {

    const customerSelect =
        document.getElementById('customer_id');

    const paymentTypeSelect =
        document.getElementById('payment_type');

    const invoiceWrapper =
        document.getElementById('invoiceWrapper');

    const invoiceSelect =
        document.getElementById('sales_invoice_id');

    const invoiceLoading =
        document.getElementById('invoiceLoading');

    const invoiceEmpty =
        document.getElementById('invoiceEmpty');

    const invoiceSummaryWrapper =
        document.getElementById('invoiceSummaryWrapper');

    const invoiceTotal =
        document.getElementById('invoiceTotal');

    const invoicePaid =
        document.getElementById('invoicePaid');

    const invoiceRemaining =
        document.getElementById('invoiceRemaining');

    const amountInput =
        document.getElementById('amount');

    const amountHelp =
        document.getElementById('amountHelp');

    const paymentStatus =
        document.getElementById('paymentStatus');

    const form =
        document.getElementById('paymentReceiptForm');


    /*
    |--------------------------------------------------------------------------
    | Existing Invoice
    |--------------------------------------------------------------------------
    */

    const existingInvoiceId =
        @json(
            old(
                'sales_invoice_id',
                $paymentReceipt?->sales_invoice_id
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Invoice Data
    |--------------------------------------------------------------------------
    */

    let invoices = [];

    let selectedInvoice = null;


    /*
    |--------------------------------------------------------------------------
    | Format Money
    |--------------------------------------------------------------------------
    */

    function money(value) {

        return Number(value || 0)
            .toLocaleString('ar-EG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

    }


    /*
    |--------------------------------------------------------------------------
    | Update Payment Status
    |--------------------------------------------------------------------------
    */

    function updatePaymentStatus() {

        const amount =
            Number(amountInput.value || 0);


        if (
            paymentTypeSelect.value === 'advance'
        ) {

            paymentStatus.innerHTML =
                '<span class="badge bg-warning text-dark">دفعة مقدمة</span>';

            amountInput.removeAttribute('max');

            amountHelp.textContent =
                'ستُسجل هذه الدفعة كدفعة مقدمة.';

            return;
        }


        if (!selectedInvoice) {

            paymentStatus.textContent = '—';

            amountInput.removeAttribute('max');

            amountHelp.textContent =
                'اختر فاتورة أولًا.';

            return;
        }


        const remaining =
            Number(
                selectedInvoice.remaining_amount || 0
            );


        amountInput.max =
            remaining.toFixed(2);


        if (amount <= 0) {

            paymentStatus.textContent = '—';

            amountHelp.textContent =
                `الحد الأقصى للدفعة: ${money(remaining)} ج.م`;

            return;
        }


        if (amount > remaining) {

            paymentStatus.innerHTML =
                '<span class="badge bg-danger">المبلغ يتجاوز الرصيد المتبقي</span>';

            amountHelp.textContent =
                `الحد الأقصى المسموح: ${money(remaining)} ج.م`;

            return;
        }


        if (
            Math.abs(amount - remaining) < 0.01
        ) {

            paymentStatus.innerHTML =
                '<span class="badge bg-success">سداد كامل</span>';

            amountHelp.textContent =
                'ستؤدي هذه الدفعة إلى سداد الفاتورة بالكامل.';

            return;
        }


        paymentStatus.innerHTML =
            '<span class="badge bg-warning text-dark">سداد جزئي</span>';

        amountHelp.textContent =
            `المتبقي بعد الدفعة: ${money(
                remaining - amount
            )} ج.م`;

    }


    /*
    |--------------------------------------------------------------------------
    | Render Invoice Options
    |--------------------------------------------------------------------------
    */

    function renderInvoices() {

        invoiceSelect.innerHTML =
            '<option value="">اختر الفاتورة</option>';


        if (!invoices.length) {

            invoiceEmpty.classList.remove('d-none');

            invoiceSummaryWrapper.classList.add('d-none');

            selectedInvoice = null;

            return;
        }


        invoiceEmpty.classList.add('d-none');


        invoices.forEach(function (invoice) {

            const option =
                document.createElement('option');

            option.value =
                invoice.id;

            option.textContent =
                `${invoice.invoice_number} — متبقي ${money(invoice.remaining_amount)} ج.م`;

            option.dataset.invoiceId =
                invoice.id;

            invoiceSelect.appendChild(option);

        });


        if (existingInvoiceId) {

            const exists =
                invoices.some(function (invoice) {

                    return String(invoice.id) ===
                        String(existingInvoiceId);

                });


            if (exists) {

                invoiceSelect.value =
                    existingInvoiceId;

                selectInvoice();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Select Invoice
    |--------------------------------------------------------------------------
    */

    function selectInvoice() {

        const invoiceId =
            invoiceSelect.value;


        selectedInvoice =
            invoices.find(function (invoice) {

                return String(invoice.id) ===
                    String(invoiceId);

            }) || null;


        if (!selectedInvoice) {

            invoiceSummaryWrapper
                .classList
                .add('d-none');

            updatePaymentStatus();

            return;
        }


        invoiceSummaryWrapper
            .classList
            .remove('d-none');


        invoiceTotal.textContent =
            `${money(selectedInvoice.total_amount)} ج.م`;


        invoicePaid.textContent =
            `${money(selectedInvoice.paid_amount)} ج.م`;


        invoiceRemaining.textContent =
            `${money(selectedInvoice.remaining_amount)} ج.م`;


        amountInput.max =
            Number(
                selectedInvoice.remaining_amount
            ).toFixed(2);


        updatePaymentStatus();

    }


    /*
    |--------------------------------------------------------------------------
    | Load Customer Invoices
    |--------------------------------------------------------------------------
    */

    async function loadCustomerInvoices(
        customerId,
        preserveSelection = false
    ) {

        invoices = [];

        selectedInvoice = null;

        invoiceSelect.innerHTML =
            '<option value="">اختر الفاتورة</option>';

        invoiceSummaryWrapper
            .classList
            .add('d-none');


        if (!customerId) {

            invoiceEmpty.classList.add('d-none');

            invoiceLoading.classList.add('d-none');

            return;
        }


        invoiceLoading.classList.remove('d-none');

        invoiceEmpty.classList.add('d-none');


        try {

            const url =
                @json(
                    route(
                        'payment-receipts.customer-invoices',
                        ['customer' => '__CUSTOMER__']
                    )
                )
                .replace(
                    '__CUSTOMER__',
                    customerId
                );


            const response =
                await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });


            if (!response.ok) {

                throw new Error(
                    'تعذر تحميل فواتير العميل.'
                );

            }


            const data =
                await response.json();


            invoices =
                Array.isArray(data.invoices)
                    ? data.invoices
                    : [];


            renderInvoices();


        } catch (error) {

            console.error(error);

            invoiceSelect.innerHTML =
                '<option value="">تعذر تحميل الفواتير</option>';

        } finally {

            invoiceLoading.classList.add('d-none');

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Toggle Payment Type
    |--------------------------------------------------------------------------
    */

    function togglePaymentType() {

        const type =
            paymentTypeSelect.value;


        if (type === 'advance') {

            invoiceWrapper
                .classList
                .add('d-none');


            invoiceSelect.value = '';

            selectedInvoice = null;

            invoiceSummaryWrapper
                .classList
                .add('d-none');


            amountInput.removeAttribute('max');

            updatePaymentStatus();

            return;
        }


        invoiceWrapper
            .classList
            .remove('d-none');


        updatePaymentStatus();

    }


    /*
    |--------------------------------------------------------------------------
    | Customer Change
    |--------------------------------------------------------------------------
    */

    customerSelect.addEventListener(
        'change',
        function () {

            loadCustomerInvoices(
                this.value
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Payment Type Change
    |--------------------------------------------------------------------------
    */

    paymentTypeSelect.addEventListener(
        'change',
        function () {

            togglePaymentType();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Invoice Change
    |--------------------------------------------------------------------------
    */

    invoiceSelect.addEventListener(
        'change',
        function () {

            selectInvoice();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Amount Change
    |--------------------------------------------------------------------------
    */

    amountInput.addEventListener(
        'input',
        function () {

            updatePaymentStatus();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Prevent Overpayment From UI
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        function (event) {

            if (
                paymentTypeSelect.value !==
                'invoice'
            ) {
                return;
            }


            if (!selectedInvoice) {

                event.preventDefault();

                alert(
                    'يرجى اختيار فاتورة مبيعات.'
                );

                return;
            }


            const amount =
                Number(
                    amountInput.value || 0
                );


            const remaining =
                Number(
                    selectedInvoice.remaining_amount || 0
                );


            if (amount > remaining) {

                event.preventDefault();

                alert(
                    `لا يمكن أن تتجاوز الدفعة الرصيد المتبقي وقدره ${money(remaining)} ج.م.`
                );

                amountInput.focus();

                return;
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */

    togglePaymentType();


    const initialCustomer =
        customerSelect.value;


    if (
        initialCustomer &&
        paymentTypeSelect.value === 'invoice'
    ) {

        loadCustomerInvoices(
            initialCustomer,
            true
        );

    }


    /*
    |--------------------------------------------------------------------------
    | If Validation Error Returned
    |--------------------------------------------------------------------------
    */

    updatePaymentStatus();

});

</script>
