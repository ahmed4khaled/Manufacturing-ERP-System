@extends('layouts.dashboard')

@section('title', 'Supplier Payment ' . ($supplierPayment->payment_number ?? ''))

@section('content')

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">

        <div>

            <div class="d-flex align-items-center gap-2 mb-1">

                <h1 class="h3 mb-0">
                    {{ $supplierPayment->payment_number ?? 'Supplier Payment' }}
                </h1>

                @if ($supplierPayment->payment_type === 'advance')

                    <span class="badge bg-warning text-dark">
                        Advance Payment
                    </span>

                @elseif ($supplierPayment->payment_type === 'invoice')

                    <span class="badge bg-primary">
                        Invoice Payment
                    </span>

                @else

                    <span class="badge bg-secondary">
                        {{ ucfirst($supplierPayment->payment_type ?? 'Payment') }}
                    </span>

                @endif

            </div>

            <p class="text-muted mb-0">
                Supplier payment made by KONOZ
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
                Print Payment
            </a>

            <a
                href="{{ route('supplier-payments.index') }}"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Back
            </a>

        </div>

    </div>


    <div class="row g-4">


        {{-- LEFT COLUMN --}}
        <div class="col-lg-4">


            {{-- Supplier --}}
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Supplier
                    </h5>

                </div>

                <div class="card-body">

                    <div class="fw-semibold fs-5 mb-1">

                        {{ $supplierPayment->supplier?->name ?? '—' }}

                    </div>


                    @if ($supplierPayment->supplier?->code)

                        <div class="text-muted small mb-2">

                            Code:
                            {{ $supplierPayment->supplier->code }}

                        </div>

                    @endif


                    @if ($supplierPayment->supplier?->phone)

                        <div class="text-muted small">

                            Phone:
                            {{ $supplierPayment->supplier->phone }}

                        </div>

                    @endif

                </div>

            </div>


            {{-- Payment Information --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Payment Information
                    </h5>

                </div>

                <div class="card-body">


                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Payment Number
                        </span>

                        <strong>
                            {{ $supplierPayment->payment_number ?? '—' }}
                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Payment Date
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
                            Payment Method
                        </span>

                        <strong>

                            {{ ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $supplierPayment->payment_method ?? ''
                                )
                            ) ?: '—' }}

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Reference
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
                            Notes
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
                        Payment Details
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Payment Type --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            Payment Type
                        </span>

                        <strong>

                            @if ($supplierPayment->payment_type === 'advance')

                                Advance Payment

                            @elseif ($supplierPayment->payment_type === 'invoice')

                                Invoice Payment

                            @else

                                {{ ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $supplierPayment->payment_type ?? 'Payment'
                                    )
                                ) }}

                            @endif

                        </strong>

                    </div>


                    {{-- Purchase Order --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            Purchase Order
                        </span>

                        <strong>

                            @if ($supplierPayment->purchaseOrder)

                                {{ $supplierPayment->purchaseOrder->purchase_order_number }}

                            @else

                                <span class="text-muted">
                                    Not linked
                                </span>

                            @endif

                        </strong>

                    </div>


                    {{-- Supplier Invoice --}}
                    <div class="d-flex justify-content-between mb-4">

                        <span class="text-muted">
                            Supplier Invoice
                        </span>

                        <strong>

                            @if ($supplierPayment->invoice)

                                {{ $supplierPayment->invoice->invoice_number }}

                            @else

                                <span class="text-muted">
                                    Not linked
                                </span>

                            @endif

                        </strong>

                    </div>


                    <hr>


                    {{-- Amount --}}
                    <div class="d-flex justify-content-between align-items-center">

                        <span class="fw-semibold fs-5">
                            Amount Paid
                        </span>

                        <strong class="fs-3 text-primary">

                            {{ number_format(
                                (float) $supplierPayment->amount,
                                2
                            ) }}

                            EGP

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Payment Statement --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Payment Statement
                    </h5>

                </div>

                <div class="card-body">

                    <div class="alert alert-light border mb-0">

                        <div class="fw-semibold mb-2">
                            Supplier Payment
                        </div>

                        <div class="text-muted">

                            KONOZ paid

                            <strong>
                                {{ number_format(
                                    (float) $supplierPayment->amount,
                                    2
                                ) }}
                                EGP
                            </strong>

                            to

                            <strong>
                                {{ $supplierPayment->supplier?->name ?? 'the supplier' }}
                            </strong>

                            @if ($supplierPayment->payment_type === 'advance')

                                as an advance payment.

                            @elseif ($supplierPayment->payment_type === 'invoice')

                                as payment against the supplier invoice.

                            @else

                                as a supplier payment.

                            @endif

                        </div>

                    </div>

                </div>

            </div>


            {{-- References --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        References
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row g-3">


                        <div class="col-md-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small mb-1">
                                    Purchase Order
                                </div>

                                <div class="fw-semibold">

                                    {{ $supplierPayment->purchaseOrder?->purchase_order_number
                                        ?? 'Not linked'
                                    }}

                                </div>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="border rounded p-3">

                                <div class="text-muted small mb-1">
                                    Supplier Invoice
                                </div>

                                <div class="fw-semibold">

                                    {{ $supplierPayment->invoice?->invoice_number
                                        ?? 'Not linked'
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
            Back to Supplier Payments
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
            Print Payment
        </a>

    </div>

@endsection