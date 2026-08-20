@extends('layouts.dashboard')

@section('title', $supplierInvoice->invoice_number . ' - فاتورة مورد')

@section('content')

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">

        <div>
            <div class="d-flex align-items-center gap-2 mb-1">

                <h1 class="h3 mb-0">
                    {{ $supplierInvoice->invoice_number }}
                </h1>

                @php
                    $statusClass = match ($supplierInvoice->status) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'unpaid' => 'secondary',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    };
                @endphp

                <span class="badge bg-{{ $statusClass }}">
                    {{ \App\Support\ArabicLabels::status($supplierInvoice->status) }}
                </span>

            </div>

            <p class="text-muted mb-0">
                فاتورة مورد
                ·
                {{ $supplierInvoice->invoice_date?->format('Y-m-d') }}
            </p>
        </div>


        <div class="d-flex gap-2">

            {{-- Print --}}
            {{-- <a
                href="{{ route('supplier-invoices.print', $supplierInvoice) }}"
                target="_blank"
                class="btn btn-outline-dark"
            >
                <i class="bi bi-printer me-1"></i>
                طباعة
            </a> --}}


            {{-- Record Payment --}}
            @if (
                bccomp(
                    (string) $supplierInvoice->remaining_amount,
                    '0',
                    2
                ) > 0
                &&
                $supplierInvoice->status !== 'cancelled'
            )

                <a
                    href="{{ route('supplier-payments.create') }}"
                    class="btn btn-primary"
                >
                    <i class="bi bi-cash-stack me-1"></i>
                    تسجيل دفعة
                </a>

            @endif

        </div>

    </div>


    <div class="row g-4">

        {{-- LEFT COLUMN --}}
        <div class="col-lg-4">


            {{-- المورد --}}
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        المورد
                    </h5>

                </div>

                <div class="card-body">

                    <p class="fw-semibold mb-1">
                        {{ $supplierInvoice->supplier?->name ?? '—' }}
                    </p>

                    @if ($supplierInvoice->supplier?->code)

                        <p class="text-muted small mb-1">
                            الكود:
                            {{ $supplierInvoice->supplier->code }}
                        </p>

                    @endif

                    @if ($supplierInvoice->supplier?->phone)

                        <p class="text-muted small mb-0">
                            الهاتف:
                            {{ $supplierInvoice->supplier->phone }}
                        </p>

                    @endif

                </div>

            </div>


            {{-- Invoice Information --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        بيانات الفاتورة
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Invoice Number --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            رقم الفاتورة
                        </span>

                        <strong>
                            {{ $supplierInvoice->invoice_number }}
                        </strong>

                    </div>


                    {{-- Invoice Date --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            تاريخ الفاتورة
                        </span>

                        <strong>
                            {{ $supplierInvoice->invoice_date?->format('Y-m-d') ?? '—' }}
                        </strong>

                    </div>


                    {{-- Due Date --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            تاريخ الاستحقاق
                        </span>

                        <strong>
                            {{ $supplierInvoice->due_date?->format('Y-m-d') ?? '—' }}
                        </strong>

                    </div>


                    {{-- Purchase Order --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            أمر الشراء
                        </span>

                        <strong>

                            @if ($supplierInvoice->purchaseOrder)

                                {{ $supplierInvoice->purchaseOrder->purchase_order_number }}

                            @else

                                <span class="text-muted">
                                    غير مرتبط
                                </span>

                            @endif

                        </strong>

                    </div>


                    {{-- Goods Receipt --}}
                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            إذن الاستلام
                        </span>

                        <strong>

                            @if ($supplierInvoice->goodsReceipt)

                                {{ $supplierInvoice->goodsReceipt->receipt_number }}

                            @else

                                <span class="text-muted">
                                    غير مرتبط
                                </span>

                            @endif

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Notes --}}
            @if ($supplierInvoice->notes)

                <div class="card mt-4">

                    <div class="card-header bg-white">

                        <h5 class="card-title mb-0">
                            ملاحظات
                        </h5>

                    </div>

                    <div class="card-body">

                        <p class="mb-0 text-muted">
                            {{ $supplierInvoice->notes }}
                        </p>

                    </div>

                </div>

            @endif

        </div>


        {{-- RIGHT COLUMN --}}
        <div class="col-lg-8">


            {{-- Invoice Summary --}}
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        ملخص الفاتورة
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Subtotal --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            الإجمالي الفرعي
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->subtotal, 2) }}
                            ج.م
                        </strong>

                    </div>


                    {{-- Discount --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            الخصم
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->discount, 2) }}
                            ج.م
                        </strong>

                    </div>


                    {{-- Tax --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            الضريبة
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->tax, 2) }}
                            ج.م
                        </strong>

                    </div>


                    <hr>


                    {{-- Total --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="fw-semibold">
                            إجمالي الفاتورة
                        </span>

                        <strong class="fs-5 text-primary">

                            {{ number_format($supplierInvoice->total, 2) }}
                            ج.م

                        </strong>

                    </div>


                    {{-- Paid --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            المبلغ المدفوع
                        </span>

                        <strong class="text-success">

                            {{ number_format($supplierInvoice->paid_amount, 2) }}
                            ج.م

                        </strong>

                    </div>


                    {{-- Remaining --}}
                    <div class="d-flex justify-content-between">

                        <span class="fw-semibold">
                            المتبقي
                        </span>

                        <strong
                            class="
                                fs-5
                                {{
                                    bccomp(
                                        (string) $supplierInvoice->remaining_amount,
                                        '0',
                                        2
                                    ) > 0
                                    ? 'text-danger'
                                    : 'text-success'
                                }}
                            "
                        >

                            {{ number_format($supplierInvoice->remaining_amount, 2) }}
                            ج.م

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Payment History --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        سجل المدفوعات
                    </h5>

                </div>

                <div class="card-body p-0">

                    @if ($supplierInvoice->payments->isNotEmpty())

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            السداد
                                        </th>

                                        <th>
                                            التاريخ
                                        </th>

                                        <th>
                                            الطريقة
                                        </th>

                                        <th>
                                            المرجع
                                        </th>

                                        <th class="text-end">
                                            المبلغ
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    @foreach ($supplierInvoice->payments as $payment)

                                        <tr>

                                            <td class="fw-semibold">
                                                {{ $payment->payment_number }}
                                            </td>

                                            <td>
                                                {{ $payment->payment_date?->format('Y-m-d') }}
                                            </td>

                                            <td>
                                                {{ \App\Support\ArabicLabels::paymentMethod($payment->payment_method) }}
                                            </td>

                                            <td>
                                                {{ $payment->reference ?? '—' }}
                                            </td>

                                            <td class="text-end">

                                                <strong>

                                                    {{ number_format(
                                                        $payment->amount,
                                                        2
                                                    ) }}

                                                    ج.م

                                                </strong>

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div class="text-center text-muted py-5">

                            لم تُسجل مدفوعات لهذه الفاتورة بعد.

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>


    {{-- Back --}}
    <div class="mt-4">

        <a
            href="{{ route('supplier-invoices.index') }}"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left me-1"></i>
            العودة إلى فواتير الموردين
        </a>

    </div>

@endsection
