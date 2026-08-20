@extends('layouts.dashboard')

@section('title', 'دفعة مورد ' . ($supplierPayment->payment_number ?? ''))

@section('content')

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">

        <div>

            <div class="d-flex align-items-center gap-2 mb-1">

                <h1 class="h3 mb-0">
                    {{ $supplierPayment->payment_number ?? 'دفعة مورد' }}
                </h1>

                @if ($supplierPayment->payment_type === 'advance')

                    <span class="badge bg-warning text-dark">
                        دفعة مقدمة
                    </span>

                @elseif ($supplierPayment->payment_type === 'invoice')

                    <span class="badge bg-primary">
                        سداد فاتورة
                    </span>

                @else

                    <span class="badge bg-secondary">
                        {{ \App\Support\ArabicLabels::paymentType($supplierPayment->payment_type) }}
                    </span>

                @endif

            </div>

            <p class="text-muted mb-0">
                دفعة مورد مسجلة بواسطة KONOZ
            </p>

        </div>


        <div class="d-flex gap-2">

            {{-- Print --}}
            <a
                href="{{ route(
                    'supplier-payments.print-payment',
                    $supplierPayment
                ) }}"
                target="_blank"
                class="btn btn-outline-dark"
            >
                <i class="bi bi-printer me-1"></i>
                طباعة الدفعة
            </a>

            <a
                href="{{ route('supplier-payments.index') }}"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                رجوع
            </a>

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

                    <div class="fw-semibold fs-5 mb-1">

                        {{ $supplierPayment->supplier?->name ?? '—' }}

                    </div>


                    @if ($supplierPayment->supplier?->code)

                        <div class="text-muted small mb-2">

                            الكود:
                            {{ $supplierPayment->supplier->code }}

                        </div>

                    @endif


                    @if ($supplierPayment->supplier?->phone)

                        <div class="text-muted small">

                            الهاتف:
                            {{ $supplierPayment->supplier->phone }}

                        </div>

                    @endif

                </div>

            </div>


            {{-- Payment Information --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        بيانات السداد
                    </h5>

                </div>

                <div class="card-body">


                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            رقم الدفعة
                        </span>

                        <strong>
                            {{ $supplierPayment->payment_number ?? '—' }}
                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            تاريخ السداد
                        </span>

                        <strong>

                            {{ $supplierPayment->payment_date
                                ? $supplierPayment->payment_date->format('Y-m-d')
                                : '—'
                            }}

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            طريقة الدفع
                        </span>

                        <strong>

                            {{ \App\Support\ArabicLabels::paymentMethod($supplierPayment->payment_method) }}

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            المرجع
                        </span>

                        <strong>

                            {{ $supplierPayment->reference ?? '—' }}

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Notes --}}
            @if ($supplierPayment->notes)

                <div class="card mt-4">

                    <div class="card-header bg-white">

                        <h5 class="card-title mb-0">
                            ملاحظات
                        </h5>

                    </div>

                    <div class="card-body">

                        <p class="mb-0 text-muted">
                            {{ $supplierPayment->notes }}
                        </p>

                    </div>

                </div>

            @endif

        </div>


        {{-- RIGHT COLUMN --}}
        <div class="col-lg-8">


            {{-- Payment Summary --}}
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        تفاصيل السداد
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Payment Type --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            نوع السداد
                        </span>

                        <strong>

                            @if ($supplierPayment->payment_type === 'advance')

                                دفعة مقدمة

                            @elseif ($supplierPayment->payment_type === 'invoice')

                                سداد فاتورة

                            @else

                                {{ \App\Support\ArabicLabels::paymentType($supplierPayment->payment_type) }}

                            @endif

                        </strong>

                    </div>


                    {{-- Purchase Order --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            أمر الشراء
                        </span>

                        <strong>

                            @if ($supplierPayment->purchaseOrder)

                                {{ $supplierPayment->purchaseOrder->purchase_order_number }}

                            @else

                                <span class="text-muted">
                                    غير مرتبط
                                </span>

                            @endif

                        </strong>

                    </div>


                    {{-- فاتورة مورد --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            فاتورة مورد
                        </span>

                        <strong>

                            @if ($supplierPayment->invoice)

                                {{ $supplierPayment->invoice->invoice_number }}

                            @else

                                <span class="text-muted">
                                    غير مرتبط
                                </span>

                            @endif

                        </strong>

                    </div>


                    <hr>


                    {{-- Amount --}}
                    <div class="d-flex justify-content-between align-items-center">

                        <span class="fw-semibold fs-5">
                            المبلغ المدفوع
                        </span>

                        <strong class="fs-3 text-primary">

                            {{ number_format(
                                (float) $supplierPayment->amount,
                                2
                            ) }}

                            ج.م

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Payment Statement --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        كشف المدفوعات
                    </h5>

                </div>

                <div class="card-body">

                    <div class="alert alert-light border mb-0">

                        <div class="fw-semibold mb-2">
                            دفعة مورد
                        </div>

                        <div class="text-muted">

                            دفعت KONOZ

                            <strong>
                                {{ number_format(
                                    (float) $supplierPayment->amount,
                                    2
                                ) }}
                                ج.م
                            </strong>

                            إلى

                            <strong>
                                {{ $supplierPayment->supplier?->name ?? 'المورد' }}
                            </strong>

                            @if ($supplierPayment->payment_type === 'advance')

                                كدفعة مقدمة.

                            @elseif ($supplierPayment->payment_type === 'invoice')

                                كسداد على فاتورة المورد.

                            @else

                                كدفعة للمورد.

                            @endif

                        </div>

                    </div>

                </div>

            </div>


            {{-- References --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        المراجع
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row g-3">


                        <div class="col-md-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small mb-1">
                                    أمر الشراء
                                </div>

                                <div class="fw-semibold">

                                    {{ $supplierPayment->purchaseOrder?->purchase_order_number
                                        ?? 'غير مرتبط'
                                    }}

                                </div>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small mb-1">
                                    فاتورة مورد
                                </div>

                                <div class="fw-semibold">

                                    {{ $supplierPayment->invoice?->invoice_number
                                        ?? 'غير مرتبط'
                                    }}

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- Bottom Actions --}}
    <div class="mt-4 d-flex gap-2">

        <a
            href="{{ route('supplier-payments.index') }}"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left me-1"></i>
            العودة إلى مدفوعات الموردين
        </a>


        <a
            href="{{ route(
                'supplier-payments.print-payment',
                $supplierPayment
            ) }}"
            target="_blank"
            class="btn btn-dark"
        >
            <i class="bi bi-printer me-1"></i>
            طباعة الدفعة
        </a>

    </div>

@endsection
