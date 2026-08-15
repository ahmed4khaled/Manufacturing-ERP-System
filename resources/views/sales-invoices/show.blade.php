@extends('layouts.dashboard')

@section('title', $salesInvoice->invoice_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">

            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}" class="text-decoration-none">
                    Dashboard
                </a>
            </li>

            <li class="breadcrumb-item">
                <a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">
                    Sales Invoices
                </a>
            </li>

            <li class="breadcrumb-item active">
                {{ $salesInvoice->invoice_number }}
            </li>

        </ol>
    </nav>
@endsection


@section('content')

    {{-- =========================================================
         HEADER
    ========================================================== --}}

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">

        <div>

            <div class="d-flex align-items-center gap-2 mb-1">

                <h1 class="h3 mb-0">
                    {{ $salesInvoice->invoice_number }}
                </h1>

                {{-- Invoice Status --}}
                <span class="badge bg-{{ $salesInvoice->status->badgeClass() }}">
                    {{ $salesInvoice->status->label() }}
                </span>

                {{-- Payment Status --}}
                @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED)

                    <span class="badge bg-{{ $salesInvoice->payment_status_badge_class }}">
                        {{ $salesInvoice->payment_status_label }}
                    </span>

                @endif

            </div>


            <p class="text-muted mb-0">

                {{ $salesInvoice->customer?->name }}

                ·

                {{ $salesInvoice->invoice_date->format('M d, Y') }}

            </p>

        </div>


        {{-- =====================================================
             ACTIONS
        ====================================================== --}}

        <div class="d-flex flex-wrap gap-2">


            {{-- Print --}}
            <a
                href="{{ route('sales-invoices.print', $salesInvoice) }}"
                target="_blank"
                class="btn btn-outline-dark"
            >

                <i class="bi bi-printer me-1"></i>

                Print

            </a>


            {{-- Receive Payment --}}
            @if (
                $salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED
                && $salesInvoice->remaining_amount > 0
            )

                <a
                    href="{{ route('payment-receipts.create') }}?customer_id={{ $salesInvoice->customer_id }}&sales_invoice_id={{ $salesInvoice->id }}"
                    class="btn btn-success"
                >

                    <i class="bi bi-cash-coin me-1"></i>

                    Receive Payment

                </a>

            @endif


            {{-- Draft Actions --}}
            @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::DRAFT)

                <a
                    href="{{ route('sales-invoices.edit', $salesInvoice) }}"
                    class="btn btn-primary"
                >

                    <i class="bi bi-pencil me-1"></i>

                    Edit

                </a>


                <form
                    action="{{ route('sales-invoices.confirm', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('Confirm this invoice? This will post the amount to the customer ledger.')"
                >

                    @csrf

                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle me-1"></i>

                        Confirm

                    </button>

                </form>


                <form
                    action="{{ route('sales-invoices.destroy', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('Delete this draft invoice?')"
                >

                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                    >

                        <i class="bi bi-trash me-1"></i>

                        Delete

                    </button>

                </form>

            @elseif ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED)

                <form
                    action="{{ route('sales-invoices.cancel', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('Cancel this invoice?')"
                >

                    @csrf

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                    >

                        <i class="bi bi-x-circle me-1"></i>

                        Cancel Invoice

                    </button>

                </form>

            @endif

        </div>

    </div>


    <div class="row g-4">


        {{-- =========================================================
             LEFT SIDE
        ========================================================== --}}

        <div class="col-lg-4">


            {{-- Customer --}}
            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Customer
                    </h5>

                </div>

                <div class="card-body">

                    <p class="fw-semibold mb-1">
                        {{ $salesInvoice->customer?->name ?? '—' }}
                    </p>

                    <p class="text-muted small mb-1">
                        {{ $salesInvoice->customer?->code ?? '—' }}
                    </p>

                    <p class="text-muted small mb-0">
                        {{ $salesInvoice->customer?->phone ?? '—' }}
                    </p>

                </div>

            </div>


            {{-- =====================================================
                 PAYMENT SUMMARY
            ====================================================== --}}

            @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED)

                <div class="card mt-4">

                    <div class="card-header bg-white">

                        <h5 class="card-title mb-0">
                            Payment Summary
                        </h5>

                    </div>

                    <div class="card-body">


                        {{-- Payment Status --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="text-muted">
                                Payment Status
                            </span>

                            <span class="badge bg-{{ $salesInvoice->payment_status_badge_class }}">
                                {{ $salesInvoice->payment_status_label }}
                            </span>

                        </div>


                        {{-- Invoice Total --}}
                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                Invoice Total
                            </span>

                            <strong>
                                {{ number_format($salesInvoice->total_amount, 2) }}
                                EGP
                            </strong>

                        </div>


                        {{-- Paid --}}
                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                Paid
                            </span>

                            <strong class="text-success">

                                {{ number_format($salesInvoice->paid_amount, 2) }}

                                EGP

                            </strong>

                        </div>


                        {{-- Remaining --}}
                        <div class="d-flex justify-content-between pt-2 border-top">

                            <span class="text-muted">
                                Remaining
                            </span>

                            <strong
                                class="{{ $salesInvoice->remaining_amount > 0 ? 'text-danger' : 'text-success' }}"
                            >

                                {{ number_format($salesInvoice->remaining_amount, 2) }}

                                EGP

                            </strong>

                        </div>


                        {{-- Receive Payment Button --}}
                        @if ($salesInvoice->remaining_amount > 0)

                            <div class="mt-3">

                                <a
                                    href="{{ route('payment-receipts.create') }}?customer_id={{ $salesInvoice->customer_id }}&sales_invoice_id={{ $salesInvoice->id }}"
                                    class="btn btn-success w-100"
                                >

                                    <i class="bi bi-cash-coin me-1"></i>

                                    Receive Payment

                                </a>

                            </div>

                        @else

                            <div class="alert alert-success mt-3 mb-0 py-2">

                                <i class="bi bi-check-circle me-1"></i>

                                Invoice fully paid.

                            </div>

                        @endif

                    </div>

                </div>

            @endif


            {{-- =====================================================
                 NOTES
            ====================================================== --}}

            @if ($salesInvoice->notes)

                <div class="card mt-4">

                    <div class="card-header bg-white">

                        <h5 class="card-title mb-0">
                            Notes
                        </h5>

                    </div>

                    <div class="card-body">

                        <p class="mb-0 text-muted">
                            {{ $salesInvoice->notes }}
                        </p>

                    </div>

                </div>

            @endif


            {{-- =====================================================
                 TOTALS
            ====================================================== --}}

            <div class="card mt-4">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Totals
                    </h5>

                </div>

                <div class="card-body">


                    <div class="d-flex justify-content-between mb-2">

                        <span class="text-muted">
                            Total Quantity
                        </span>

                        <strong>
                            {{ number_format($salesInvoice->total_quantity) }}
                            Pieces
                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Invoice Total
                        </span>

                        <strong class="fs-5 text-primary">

                            {{ number_format($salesInvoice->total_amount, 2) }}

                            EGP

                        </strong>

                    </div>

                </div>

            </div>

        </div>


        {{-- =========================================================
             ITEMS
        ========================================================== --}}

        <div class="col-lg-8">

            <div class="card">

                <div class="card-header bg-white">

                    <h5 class="card-title mb-0">
                        Invoice Items (by Model)
                    </h5>

                </div>


                <div class="card-body p-0">

                    @foreach ($groupedItems as $group)

                        <div class="border-bottom p-3">


                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <div>

                                    <h6 class="mb-0">
                                        {{ $group['product_name'] }}
                                    </h6>

                                </div>


                                <div class="text-end">

                                    <strong>
                                        {{ number_format($group['total_quantity']) }}
                                        Pieces
                                    </strong>

                                    <br>

                                    <span class="text-primary fw-semibold">

                                        {{ number_format($group['total_amount'], 2) }}

                                        EGP

                                    </span>

                                </div>

                            </div>


                            <div class="table-responsive">

                                <table class="table table-sm table-bordered mb-0">

                                    <thead class="table-light">

                                        <tr>

                                            <th>
                                                Color
                                            </th>

                                            <th>
                                                Size
                                            </th>

                                            <th>
                                                SKU
                                            </th>

                                            <th>
                                                Barcode
                                            </th>

                                            <th class="text-end">
                                                Unit Price
                                            </th>

                                            <th class="text-end">
                                                Qty
                                            </th>

                                            <th class="text-end">
                                                Line Total
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        @foreach ($group['items'] as $item)

                                            <tr>

                                                <td>
                                                    {{ $item->color_name }}
                                                </td>

                                                <td>
                                                    {{ $item->size_name }}
                                                </td>

                                                <td>
                                                    <code>
                                                        {{ $item->sku }}
                                                    </code>
                                                </td>

                                                <td>
                                                    {{ $item->barcode ?? '—' }}
                                                </td>

                                                <td class="text-end">

                                                    {{ number_format(
                                                        $item->unit_price,
                                                        2
                                                    ) }}

                                                </td>

                                                <td class="text-end">

                                                    {{ $item->quantity }}

                                                </td>

                                                <td class="text-end">

                                                    {{ number_format(
                                                        $item->line_total,
                                                        2
                                                    ) }}

                                                </td>

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         BACK
    ========================================================== --}}

    <div class="mt-3">

        <a
            href="{{ route('sales-invoices.index') }}"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Back to Invoices

        </a>

    </div>

@endsection