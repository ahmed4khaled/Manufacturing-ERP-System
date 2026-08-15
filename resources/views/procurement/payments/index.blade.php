@extends('layouts.dashboard')

@section('title', 'Supplier Payments')

@section('content')

    <div class="page-header d-flex justify-content-between align-items-center">

        <div>
            <h1 class="h3 mb-1">
                Supplier Payments
            </h1>

            <p class="text-muted mb-0">
                Payment history for supplier invoices and advances
            </p>
        </div>

        <a
            href="{{ route('supplier-payments.create') }}"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Record Payment
        </a>

    </div>


    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                Payment No.
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Supplier
                            </th>

                            <th>
                                Type
                            </th>

                            <th>
                                Purchase Order
                            </th>

                            <th>
                                Invoice
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

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse($payments as $payment)

                            <tr>

                                {{-- Payment Number --}}
                                <td>

                                    <strong>
                                        {{ $payment->payment_number ?? '—' }}
                                    </strong>

                                </td>


                                {{-- Date --}}
                                <td>

                                    {{ $payment->payment_date
                                        ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d')
                                        : '—'
                                    }}

                                </td>


                                {{-- Supplier --}}
                                <td>

                                    {{ $payment->supplier?->name ?? '—' }}

                                </td>


                                {{-- Payment Type --}}
                                <td>

                                    @if($payment->payment_type === 'advance')

                                        <span class="badge bg-warning text-dark">
                                            Advance
                                        </span>

                                    @elseif($payment->payment_type === 'invoice')

                                        <span class="badge bg-primary">
                                            Invoice Payment
                                        </span>

                                    @else

                                        <span class="badge bg-secondary">
                                            {{ ucfirst($payment->payment_type ?? 'Unknown') }}
                                        </span>

                                    @endif

                                </td>


                                {{-- Purchase Order --}}
                                <td>

                                    @if($payment->purchaseOrder)

                                        {{ $payment->purchaseOrder->purchase_order_number }}

                                    @else

                                        <span class="text-muted">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- Supplier Invoice --}}
                                <td>

                                    @if($payment->invoice)

                                        {{ $payment->invoice->invoice_number }}

                                    @else

                                        <span class="text-muted">
                                            Not linked
                                        </span>

                                    @endif

                                </td>


                                {{-- Payment Method --}}
                                <td>

                                    {{ ucfirst(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $payment->payment_method ?? ''
                                        )
                                    ) ?: '—' }}

                                </td>


                                {{-- Reference --}}
                                <td>

                                    {{ $payment->reference ?? '—' }}

                                </td>


                                {{-- Amount --}}
                                <td class="text-end">

                                    <strong>

                                        {{ number_format(
                                            (float) $payment->amount,
                                            2
                                        ) }}

                                        EGP

                                    </strong>

                                </td>


                                {{-- Actions --}}
                                <td class="text-end">

                                    <div class="d-flex justify-content-end gap-1">


                                        {{-- Show --}}
                                        <a
                                            href="{{ route(
                                                'supplier-payments.show-payment',
                                                $payment
                                            ) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Payment"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>


                                        {{-- Print --}}
                                        <a
                                            href="{{ route(
                                                'supplier-payments.print-payment',
                                                $payment
                                            ) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-dark"
                                            title="Print Payment"
                                        >
                                            <i class="bi bi-printer"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="10"
                                    class="text-center text-muted py-5"
                                >
                                    No supplier payments yet.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            @if($payments->hasPages())

                <div class="mt-3">

                    {{ $payments->links('pagination::bootstrap-5') }}

                </div>

            @endif

        </div>

    </div>

@endsection