@extends('layouts.dashboard')

@section('title', $purchaseOrder->purchase_order_number)

@section('content')

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">{{ $purchaseOrder->purchase_order_number }}</h1>
            <p class="text-muted mb-0">
                {{ $purchaseOrder->supplier->name }}
                ·
                {{ $purchaseOrder->order_date->format('Y-m-d') }}
            </p>
        </div>

        <span class="badge bg-secondary fs-6">
            {{ \App\Support\ArabicLabels::status($purchaseOrder->status) }}
        </span>
    </div>

    <div class="row g-4">

        {{-- LEFT COLUMN --}}
        <div class="col-lg-8">

            {{-- ORDER ITEMS --}}
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">بنود الطلب وحالة الاستلام</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>الخامة</th>
                                <th>التصنيف</th>
                                <th>الوحدة</th>
                                <th class="text-end">المطلوب</th>
                                <th class="text-end">المستلم</th>
                                <th class="text-end">المقبول</th>
                                <th class="text-end">المرفوض</th>
                                <th class="text-end">المتبقي</th>
                                <th class="text-end">السعر</th>
                                <th class="text-end">الإجمالي</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                @php
                                    $confirmedReceiptItems = $item->receiptItems->filter(
                                        fn($receiptItem) => $receiptItem->receipt?->status === 'confirmed',
                                    );
                                    $received = (float) $confirmedReceiptItems->sum('received_quantity');
                                    $accepted = (float) $confirmedReceiptItems->sum('accepted_quantity');
                                    $rejected = (float) $confirmedReceiptItems->sum('rejected_quantity');

                                    $remaining = max(0, (float) $item->quantity - $accepted);

                                    /*
                                     * Total is based ONLY on accepted quantity.
                                     */
                                    $lineTotal = $accepted * (float) $item->unit_price;
                                @endphp

                                <tr>

                                    {{-- Material --}}
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $item->material->code }}
                                        </div>

                                        <small class="text-muted">
                                            {{ $item->material->name }}
                                        </small>
                                    </td>

                                    {{-- Category --}}
                                    <td>
                                        {{ $item->material->category->label() }}
                                    </td>

                                    {{-- Unit --}}
                                    <td>
                                        {{ $item->material->unit->label() }}
                                    </td>

                                    {{-- Ordered --}}
                                    <td class="text-end">
                                        {{ number_format($item->quantity, 3) }}
                                    </td>

                                    {{-- Received --}}
                                    <td class="text-end">
                                        {{ number_format($received, 3) }}
                                    </td>

                                    {{-- Accepted --}}
                                    <td class="text-end text-success fw-semibold">
                                        {{ number_format($accepted, 3) }}
                                    </td>

                                    {{-- Rejected --}}
                                    <td class="text-end text-danger fw-semibold">
                                        {{ number_format($rejected, 3) }}
                                    </td>

                                    {{-- Remaining --}}
                                    <td class="text-end">
                                        @if ($remaining > 0)
                                            <span class="badge bg-warning text-dark">
                                                {{ number_format($remaining, 3) }}
                                            </span>
                                        @else
                                            <span class="badge bg-success">
                                                0
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Price --}}
                                    <td class="text-end">
                                        {{ number_format($item->unit_price, 2) }}
                                    </td>

                                    {{-- Total --}}
                                    <td class="text-end">
{{ number_format($lineTotal, 2) }}                              
      </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>


            {{-- GOODS RECEIPTS --}}
            <div class="card mt-4">

                <div class="card-header bg-white">
                    <h5 class="mb-0">أذون استلام الخامات</h5>
                </div>

                <div class="card-body">

                    @forelse ($purchaseOrder->receipts as $receipt)

                        <div class="border-bottom py-3">

                            {{-- Receipt Header --}}
                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <div>
                                    <strong>
                                        {{ $receipt->receipt_number }}
                                    </strong>

                                    <span class="text-muted">
                                        ·
                                        {{ $receipt->received_at->format('Y-m-d') }}
                                    </span>
                                </div>

                                <span class="badge bg-{{ $receipt->status === 'confirmed' ? 'success' : 'secondary' }}">
                                    {{ \App\Support\ArabicLabels::status($receipt->status) }}
                                </span>

                            </div>


                            {{-- Receipt Items --}}
                            @foreach ($receipt->items as $receiptItem)
                                <div class="row g-3 small mb-3">

                                    {{-- Material --}}
                                    <div class="col-md-3">
                                        <span class="text-muted">الخامة</span>

                                        <div class="fw-semibold">
                                            {{ $receiptItem->material->name }}
                                        </div>
                                    </div>

                                    {{-- Received --}}
                                    <div class="col-md-2">
                                        <span class="text-muted">المستلم</span>

                                        <div>
                                            {{ number_format($receiptItem->received_quantity, 3) }}
                                        </div>
                                    </div>

                                    {{-- Accepted --}}
                                    <div class="col-md-2">
                                        <span class="text-muted">المقبول</span>

                                        <div class="text-success fw-semibold">
                                            {{ number_format($receiptItem->accepted_quantity, 3) }}
                                        </div>
                                    </div>

                                    {{-- Rejected --}}
                                    <div class="col-md-2">
                                        <span class="text-muted">المرفوض</span>

                                        <div class="text-danger fw-semibold">
                                            {{ number_format($receiptItem->rejected_quantity, 3) }}
                                        </div>
                                    </div>

                                    {{-- Unit Cost --}}
                                    <div class="col-md-3">
                                        <span class="text-muted">تكلفة الوحدة</span>

                                        <div>
                                            {{ number_format($receiptItem->unit_cost, 2) }}
                                        </div>
                                    </div>

                                </div>
                            @endforeach

                        </div>

                    @empty

                        <p class="text-muted mb-0">
                            لم تُسجل أذون استلام بعد.
                        </p>

                    @endforelse

                </div>
            </div>

        </div>


        {{-- RIGHT COLUMN --}}
        <div class="col-lg-4">
@php
    $acceptedSubtotal = $purchaseOrder->items->sum(function ($item) {
        $confirmedReceiptItems = $item->receiptItems->filter(
            fn ($receiptItem) => $receiptItem->receipt?->status === 'confirmed'
        );

        $accepted = (float) $confirmedReceiptItems->sum('accepted_quantity');

        return $accepted * (float) $item->unit_price;
    });

    $discount = (float) $purchaseOrder->discount;
    $tax = (float) $purchaseOrder->tax;

    $calculatedTotal = $acceptedSubtotal - $discount + $tax;
@endphp
            {{-- TOTALS --}}
            <div class="card">

                <div class="card-header bg-white">
                    <h5 class="mb-0">الإجماليات</h5>
                </div>

                <div class="card-body">

                    <div class="d-flex justify-content-between mb-2">
                        <span>الإجمالي الفرعي</span>

                        <span>
{{ number_format($acceptedSubtotal, 2) }}                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>الخصم</span>

                        <span>
                            {{ number_format($purchaseOrder->discount, 2) }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>الضريبة</span>

                        <span>
                            {{ number_format($purchaseOrder->tax, 2) }}
                        </span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between fw-bold">
                        <span>الإجمالي</span>

                        <span>
{{ number_format($calculatedTotal, 2) }}                        </span>
                    </div>

                </div>
            </div>


            {{-- RECEIVING SUMMARY --}}
            <div class="card mt-4">

                <div class="card-header bg-white">
                    <h5 class="mb-0">ملخص الاستلام</h5>
                </div>

                <div class="card-body">

                    @php
                        $totalOrdered = $purchaseOrder->items->sum('quantity');

                        $confirmedReceiptItems = $purchaseOrder->items->flatMap->receiptItems->filter(
                            fn($receiptItem) => $receiptItem->receipt?->status === 'confirmed',
                        );

                        $totalReceived = $confirmedReceiptItems->sum('received_quantity');
                        $totalAccepted = $confirmedReceiptItems->sum('accepted_quantity');
                        $totalRejected = $confirmedReceiptItems->sum('rejected_quantity');

                        $totalRemaining = max(0, (float) $totalOrdered - (float) $totalReceived);
                    @endphp

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">المطلوب</span>
                        <span class="fw-semibold">
                            {{ number_format($totalOrdered, 3) }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">المستلم</span>
                        <span class="fw-semibold">
                            {{ number_format($totalReceived, 3) }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">المقبول</span>
                        <span class="fw-semibold text-success">
                            {{ number_format($totalAccepted, 3) }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">المرفوض</span>
                        <span class="fw-semibold text-danger">
                            {{ number_format($totalRejected, 3) }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-muted">المتبقي</span>

                        @if ($totalRemaining > 0)
                            <span class="badge bg-warning text-dark">
                                {{ number_format($totalRemaining, 3) }}
                            </span>
                        @else
                            <span class="badge bg-success">
                                0
                            </span>
                        @endif
                    </div>

                </div>
            </div>


            {{-- DETAILS --}}
            <div class="card mt-4">

                <div class="card-header bg-white">
                    <h5 class="mb-0">التفاصيل</h5>
                </div>

                <div class="card-body">

                    <small class="text-muted">
                        التسليم المتوقع
                    </small>

                    <div>
                        {{ $purchaseOrder->expected_delivery_date?->format('Y-m-d') ?? '—' }}
                    </div>


                    <small class="text-muted d-block mt-3">
                        ملاحظات
                    </small>

                    <div>
                        {{ $purchaseOrder->notes ?? '—' }}
                    </div>

                </div>
            </div>


            {{-- ACTIONS --}}
            <div class="card mt-4">

                <div class="card-header bg-white">
                    <h5 class="mb-0">الإجراءات</h5>
                </div>

                <div class="card-body">

                    {{-- Approve --}}
                    @if ($purchaseOrder->status === 'draft')
                        <form method="POST" action="{{ route('purchase-orders.approve', $purchaseOrder) }}"
                            style="display: inline;">
                            @csrf

                            <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                                <i class="bi bi-check2 me-1"></i>
                                اعتماد
                            </button>
                        </form>
                    @endif


                    {{-- Create Goods Receipt --}}
                    @if (in_array($purchaseOrder->status, ['approved', 'partially_received']))
                        <a href="{{ route('goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}"
                            class="btn btn-info btn-sm w-100 mb-2">
                            <i class="bi bi-inbox me-1"></i> إنشاء إذن استلام
                        </a>
                    @endif


                    {{-- Fully Received --}}
                    @if ($purchaseOrder->status === 'fully_received')
                        <div class="alert alert-success mb-0">

                            <small>
                                <i class="bi bi-check-circle me-1"></i>
                                تم استلام كل الكميات
                            </small>

                        </div>
                    @endif


                    {{-- Cancelled --}}
                    @if ($purchaseOrder->status === 'cancelled')
                        <div class="alert alert-danger mb-0">

                            <small>
                                <i class="bi bi-exclamation-circle me-1"></i>
                                أمر الشراء ملغي
                            </small>

                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>


    {{-- BACK --}}
    <div class="mt-3">

        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            العودة إلى أوامر الشراء
        </a>

    </div>

@endsection
