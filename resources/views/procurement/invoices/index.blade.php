@extends('layouts.dashboard')

@section('title', 'فواتير الموردين')

@section('content')

    <div class="page-header d-flex justify-content-between align-items-center">

        <div>
            <h1 class="h3 mb-1">
                فواتير الموردين
            </h1>

            <p class="text-muted mb-0">
                مستحقات في انتظار السداد
            </p>
        </div>

        <a
            href="{{ route('supplier-invoices.create') }}"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            إنشاء فاتورة
        </a>

    </div>


    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                الفاتورة
                            </th>

                            <th>
                                المورد
                            </th>

                            <th>
                                تاريخ الفاتورة
                            </th>

                            <th>
                                تاريخ الاستحقاق
                            </th>

                            <th>
                                الحالة
                            </th>

                            <th>الاستلام</th>

                            <th class="text-end">
                                الإجمالي
                            </th>

                            <th class="text-end">
                                المتبقي
                            </th>

                            <th class="text-end">
                                الإجراءات
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

                                <td>
                                    @if ($invoice->goodsReceipt)
                                        <a href="{{ route('goods-receipts.show', $invoice->goodsReceipt) }}" class="badge bg-success text-decoration-none">
                                            {{ $invoice->goodsReceipt->receipt_number }}
                                        </a>
                                    @elseif ($invoice->items->isNotEmpty())
                                        <span class="badge bg-warning text-dark">في الانتظار</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>


                                {{-- المورد --}}
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
                                        {{ \App\Support\ArabicLabels::status($invoice->status) }}
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
                                            title="عرض الفاتورة"
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
                                            title="طباعة الفاتورة"
                                        >
                                            <i class="bi bi-printer"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="9"
                                    class="text-center text-muted py-5"
                                >
                                    لا توجد فواتير موردين حتى الآن.
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
