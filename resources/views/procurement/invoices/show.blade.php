@extends('layouts.dashboard')

@section('title', $supplierInvoice->invoice_number . ' - Supplier Invoice')

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
                    {{ str_replace('_', ' ', ucfirst($supplierInvoice->status)) }}
                </span>

            </div>

            <p class="text-muted mb-0">
                Supplier Invoice
                ·
                {{ $supplierInvoice->invoice_date?->format('M d, Y') }}
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
                Print
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
                    Record Payment
                </a>

            @endif

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

                    <p class="fw-semibold mb-1">
                        {{ $supplierInvoice->supplier?->name ?? '—' }}
                    </p>

                    @if ($supplierInvoice->supplier?->code)

                        <p class="text-muted small mb-1">
                            Code:
                            {{ $supplierInvoice->supplier->code }}
                        </p>

                    @endif

                    @if ($supplierInvoice->supplier?->phone)

                        <p class="text-muted small mb-0">
                            Phone:
                            {{ $supplierInvoice->supplier->phone }}
                        </p>

                    @endif

                </div>

            </div>


            {{-- Invoice Information --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Invoice Information
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Invoice Number --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Invoice Number
                        </span>

                        <strong>
                            {{ $supplierInvoice->invoice_number }}
                        </strong>

                    </div>


                    {{-- Invoice Date --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Invoice Date
                        </span>

                        <strong>
                            {{ $supplierInvoice->invoice_date?->format('Y-m-d') ?? '—' }}
                        </strong>

                    </div>


                    {{-- Due Date --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Due Date
                        </span>

                        <strong>
                            {{ $supplierInvoice->due_date?->format('Y-m-d') ?? '—' }}
                        </strong>

                    </div>


                    {{-- Purchase Order --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Purchase Order
                        </span>

                        <strong>

                            @if ($supplierInvoice->purchaseOrder)

                                {{ $supplierInvoice->purchaseOrder->purchase_order_number }}

                            @else

                                <span class="text-muted">
                                    Not linked
                                </span>

                            @endif

                        </strong>

                    </div>


                    {{-- Goods Receipt --}}
                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Goods Receipt
                        </span>

                        <strong>

                            @if ($supplierInvoice->goodsReceipt)

                                {{ $supplierInvoice->goodsReceipt->receipt_number }}

                            @else

                                <span class="text-muted">
                                    Not linked
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
                            Notes
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
                        Invoice Summary
                    </h5>

                </div>

                <div class="card-body">


                    {{-- Subtotal --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Subtotal
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->subtotal, 2) }}
                            EGP
                        </strong>

                    </div>


                    {{-- Discount --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Discount
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->discount, 2) }}
                            EGP
                        </strong>

                    </div>


                    {{-- Tax --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Tax
                        </span>

                        <strong>
                            {{ number_format($supplierInvoice->tax, 2) }}
                            EGP
                        </strong>

                    </div>


                    <hr>


                    {{-- Total --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="fw-semibold">
                            Invoice Total
                        </span>

                        <strong class="fs-5 text-primary">

                            {{ number_format($supplierInvoice->total, 2) }}
                            EGP

                        </strong>

                    </div>


                    {{-- Paid --}}
                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Paid Amount
                        </span>

                        <strong class="text-success">

                            {{ number_format($supplierInvoice->paid_amount, 2) }}
                            EGP

                        </strong>

                    </div>


                    {{-- Remaining --}}
                    <div class="d-flex justify-content-between">

                        <span class="fw-semibold">
                            Remaining
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
                            EGP

                        </strong>

                    </div>

                </div>

            </div>


            {{-- Payment History --}}
            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Payment History
                    </h5>

                </div>

                <div class="card-body p-0">

                    @if ($supplierInvoice->payments->isNotEmpty())

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Payment
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Method
                                        </th>

                                        <th>
                                            Reference
                                        </th>

                                        <th class="text-end">
                                            Amount
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
                                                {{ ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $payment->payment_method
                                                    )
                                                ) }}
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

                                                    EGP

                                                </strong>

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div class="text-center text-muted py-5">

                            No payments recorded for this invoice yet.

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
            Back to Supplier Invoices
        </a>

    </div>

@endsection