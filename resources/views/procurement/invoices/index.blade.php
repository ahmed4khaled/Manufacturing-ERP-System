@extends('layouts.dashboard')

@section('title', 'Supplier Invoices')

@section('content')

    <div class="page-header d-flex justify-content-between align-items-center">

        <div>
            <h1 class="h3 mb-1">
                Supplier Invoices
            </h1>

            <p class="text-muted mb-0">
                Payables awaiting settlement
            </p>
        </div>

        <a
            href="{{ route('supplier-invoices.create') }}"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Create Invoice
        </a>

    </div>


    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                Invoice
                            </th>

                            <th>
                                Supplier
                            </th>

                            <th>
                                Invoice Date
                            </th>

                            <th>
                                Due Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th class="text-end">
                                Total
                            </th>

                            <th class="text-end">
                                Remaining
                            </th>

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse($invoices as $invoice)

                            <tr>

                                {{-- Invoice --}}
                                <td class="fw-semibold">

                                    {{ $invoice->invoice_number }}

                                </td>


                                {{-- Supplier --}}
                                <td>

                                    {{ $invoice->supplier->name }}

                                </td>


                                {{-- Invoice Date --}}
                                <td>

                                    {{ $invoice->invoice_date->format('Y-m-d') }}

                                </td>


                                {{-- Due Date --}}
                                <td>

                                    {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}

                                </td>


                                {{-- Status --}}
                                <td>

                                    @php

                                        $statusClass = match ($invoice->status) {

                                            'paid' =>
                                                'success',

                                            'partially_paid' =>
                                                'warning',

                                            'unpaid' =>
                                                'secondary',

                                            'cancelled' =>
                                                'danger',

                                            default =>
                                                'secondary',

                                        };

                                    @endphp

                                    <span
                                        class="badge bg-{{ $statusClass }}"
                                    >
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            ucfirst($invoice->status)
                                        ) }}
                                    </span>

                                </td>


                                {{-- Total --}}
                                <td class="text-end">

                                    {{ number_format($invoice->total, 2) }}

                                </td>


                                {{-- Remaining --}}
                                <td class="text-end">

                                    <strong
                                        class="{{ bccomp((string) $invoice->remaining_amount, '0', 2) > 0
                                            ? 'text-danger'
                                            : 'text-success' }}"
                                    >
                                        {{ number_format($invoice->remaining_amount, 2) }}
                                    </strong>

                                </td>


                                {{-- Actions --}}
                                <td class="text-end">

                                    <div
                                        class="d-flex justify-content-end gap-1"
                                    >

                                        {{-- Show --}}
                                        <a
                                            href="{{ route(
                                                'supplier-invoices.show',
                                                $invoice
                                            ) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Invoice"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>


                                        {{-- Print --}}
                                        <a
                                            href="{{ route(
                                                'supplier-invoices.print',
                                                $invoice
                                            ) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-dark"
                                            title="Print Invoice"
                                        >
                                            <i class="bi bi-printer"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center text-muted py-5"
                                >
                                    No supplier invoices yet.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-3">

                {{ $invoices->links('pagination::bootstrap-5') }}

            </div>

        </div>

    </div>

@endsection