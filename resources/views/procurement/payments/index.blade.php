@extends('layouts.dashboard')

@section('title', 'مدفوعات الموردين')

@section('content')

    <div class="page-header d-flex justify-content-between align-items-center">

        <div>
            <h1 class="h3 mb-1">
                مدفوعات الموردين
            </h1>

            <p class="text-muted mb-0">
                سجل مدفوعات فواتير الموردين والدفعات المقدمة
            </p>
        </div>

        <a
            href="{{ route('supplier-payments.create') }}"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            تسجيل دفعة
        </a>

    </div>


    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                رقم الدفعة
                            </th>

                            <th>
                                التاريخ
                            </th>

                            <th>
                                المورد
                            </th>

                            <th>
                                النوع
                            </th>

                            <th>
                                أمر الشراء
                            </th>

                            <th>
                                الفاتورة
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

                            <th class="text-end">
                                الإجراءات
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


                                {{-- المورد --}}
                                <td>

                                    {{ $payment->supplier?->name ?? '—' }}

                                </td>


                                {{-- Payment Type --}}
                                <td>

                                    @if($payment->payment_type === 'advance')

                                        <span class="badge bg-warning text-dark">
                                            دفعة مقدمة
                                        </span>

                                    @elseif($payment->payment_type === 'invoice')

                                        <span class="badge bg-primary">
                                            سداد فاتورة
                                        </span>

                                    @else

                                        <span class="badge bg-secondary">
                                            {{ \App\Support\ArabicLabels::paymentType($payment->payment_type) }}
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


                                {{-- فاتورة مورد --}}
                                <td>

                                    @if($payment->invoice)

                                        {{ $payment->invoice->invoice_number }}

                                    @else

                                        <span class="text-muted">
                                            غير مرتبط
                                        </span>

                                    @endif

                                </td>


                                {{-- Payment Method --}}
                                <td>

                                    {{ \App\Support\ArabicLabels::paymentMethod($payment->payment_method) }}

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

                                        ج.م

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
                                            title="عرض الدفعة"
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
                                            title="طباعة الدفعة"
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
                                    لا توجد مدفوعات موردين حتى الآن.
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
