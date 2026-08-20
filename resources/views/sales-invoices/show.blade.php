@extends('layouts.dashboard')

@section('title', $salesInvoice->invoice_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">

            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}" class="text-decoration-none">
                    لوحة التحكم
                </a>
            </li>

            <li class="breadcrumb-item">
                <a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">
                    فواتير المبيعات
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

                {{ $salesInvoice->invoice_date->format('Y-m-d') }}

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

                طباعة

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

                    تسجيل دفعة

                </a>

            @endif

            @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED)
                <a
                    href="{{ route('sales-returns.create', ['sales_invoice_id' => $salesInvoice->id]) }}"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-arrow-return-right me-1"></i>
                    إنشاء مرتجع
                </a>
            @endif


            {{-- Draft Actions --}}
            @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::DRAFT)

                <a
                    href="{{ route('sales-invoices.edit', $salesInvoice) }}"
                    class="btn btn-primary"
                >

                    <i class="bi bi-pencil me-1"></i>

                    تعديل

                </a>


                <form
                    action="{{ route('sales-invoices.confirm', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('هل تريد تأكيد الفاتورة؟ سيتم ترحيل المبلغ إلى حساب العميل.')"
                >

                    @csrf

                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle me-1"></i>

                        تأكيد

                    </button>

                </form>


                <form
                    action="{{ route('sales-invoices.destroy', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('هل تريد حذف مسودة الفاتورة؟')"
                >

                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                    >

                        <i class="bi bi-trash me-1"></i>

                        حذف

                    </button>

                </form>

            @elseif ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::CONFIRMED)

                <form
                    action="{{ route('sales-invoices.cancel', $salesInvoice) }}"
                    method="POST"
                    onsubmit="return confirm('هل تريد إلغاء هذه الفاتورة؟')"
                >

                    @csrf

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                    >

                        <i class="bi bi-x-circle me-1"></i>

                        إلغاء الفاتورة

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
                        العميل
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
                            ملخص السداد
                        </h5>

                    </div>

                    <div class="card-body">


                        {{-- Payment Status --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <span class="text-muted">
                                حالة السداد
                            </span>

                            <span class="badge bg-{{ $salesInvoice->payment_status_badge_class }}">
                                {{ $salesInvoice->payment_status_label }}
                            </span>

                        </div>


                        {{-- Invoice Total --}}
                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                إجمالي الفاتورة
                            </span>

                            <strong>
                                {{ number_format($salesInvoice->total_amount, 2) }}
                                ج.م
                            </strong>

                        </div>


                        {{-- Returns --}}
                        @if ($salesInvoice->returned_amount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">مرتجعات مؤكدة</span>
                                <strong class="text-primary">
                                    {{ number_format($salesInvoice->returned_amount, 2) }}
                                    ج.م
                                </strong>
                            </div>
                        @endif


                        {{-- Paid --}}
                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                مدفوع
                            </span>

                            <strong class="text-success">

                                {{ number_format($salesInvoice->paid_amount, 2) }}

                                ج.م

                            </strong>

                        </div>


                        {{-- Remaining --}}
                        <div class="d-flex justify-content-between pt-2 border-top">

                            <span class="text-muted">
                                المتبقي
                            </span>

                            <strong
                                class="{{ $salesInvoice->remaining_amount > 0 ? 'text-danger' : 'text-success' }}"
                            >

                                {{ number_format($salesInvoice->remaining_amount, 2) }}

                                ج.م

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

                                    تسجيل دفعة

                                </a>

                            </div>

                        @else

                            <div class="alert alert-success mt-3 mb-0 py-2">

                                <i class="bi bi-check-circle me-1"></i>

                                الفاتورة مسددة بالكامل.

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
                            ملاحظات
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
                        الإجماليات
                    </h5>

                </div>

                <div class="card-body">


                    <div class="d-flex justify-content-between mb-2">

                        <span class="text-muted">
                            إجمالي الكمية
                        </span>

                        <strong>
                            {{ number_format($salesInvoice->total_quantity, 3) }}
                            قطعة
                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            إجمالي الفاتورة
                        </span>

                        <strong class="fs-5 text-primary">

                            {{ number_format($salesInvoice->total_amount, 2) }}

                            ج.م

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
                        بنود الفاتورة (حسب الموديل)
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
                                        {{ number_format($group['total_quantity'], 3) }}
                                        قطعة
                                    </strong>

                                    <br>

                                    <span class="text-primary fw-semibold">

                                        {{ number_format($group['total_amount'], 2) }}

                                        ج.م

                                    </span>

                                </div>

                            </div>


                            <div class="table-responsive">

                                <table class="table table-sm table-bordered mb-0">

                                    <thead class="table-light">

                                        <tr>

                                            <th>
                                                اللون
                                            </th>

                                            <th>
                                                المقاس
                                            </th>

                                            <th>
                                                رمز المخزون
                                            </th>

                                            <th>
                                                الباركود
                                            </th>

                                            <th class="text-end">
                                                سعر الوحدة
                                            </th>

                                            <th class="text-end">
                                                الكمية
                                            </th>

                                            <th class="text-end">
                                                إجمالي البند
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

                                                    {{ number_format($item->quantity, 3) }}

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

            العودة إلى الفواتير

        </a>

    </div>

@endsection
