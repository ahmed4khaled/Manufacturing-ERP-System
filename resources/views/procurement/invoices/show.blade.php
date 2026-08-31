@extends('layouts.dashboard')

@section('title', $supplierInvoice->invoice_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="{{ route('supplier-invoices.index') }}">فواتير الموردين</a></li>
        <li class="breadcrumb-item active">{{ $supplierInvoice->invoice_number }}</li>
    </ol></nav>
@endsection

@section('content')
    @php
        $relatedPayments = $supplierInvoice->paymentAllocations->pluck('payment')->filter()
            ->merge($supplierInvoice->payments)->unique('id')->sortByDesc('payment_date')->values();
        $invoiceStatusClass = match ($supplierInvoice->status) {
            'paid' => 'success', 'partially_paid' => 'warning', 'cancelled' => 'danger', default => 'secondary',
        };
    @endphp

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $supplierInvoice->invoice_number }}</h1>
                <span class="badge bg-{{ $invoiceStatusClass }}">{{ \App\Support\ArabicLabels::status($supplierInvoice->status) }}</span>
                @if($supplierInvoice->status !== 'cancelled')<span class="badge bg-{{ $supplierInvoice->payment_status_badge_class }}">{{ $supplierInvoice->payment_status_label }}</span>@endif
                @if($supplierInvoice->goodsReceipt)<span class="badge bg-success">تم الاستلام</span>@elseif($supplierInvoice->items->isNotEmpty())<span class="badge bg-warning text-dark">بانتظار الاستلام</span>@endif
            </div>
            <p class="text-muted mb-0">{{ $supplierInvoice->supplier?->name }} · {{ $supplierInvoice->invoice_date?->format('Y-m-d') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('supplier-invoices.print', $supplierInvoice) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer me-1"></i> طباعة</a>
            @if(! $supplierInvoice->goodsReceipt && $supplierInvoice->items->isNotEmpty())
                <form method="POST" action="{{ route('supplier-invoices.receive', $supplierInvoice) }}">@csrf<button class="btn btn-outline-success"><i class="bi bi-box-arrow-in-down me-1"></i> استلام الخامات</button></form>
            @endif
            @if(bccomp((string) $supplierInvoice->remaining_amount, '0.00', 2) > 0 && $supplierInvoice->status !== 'cancelled')
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplierInvoice->supplier_id, 'supplier_invoice_id' => $supplierInvoice->id, 'amount' => $supplierInvoice->remaining_amount]) }}" class="btn btn-success"><i class="bi bi-cash-stack me-1"></i> تسجيل دفعة</a>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">المورد</h5></div><div class="card-body">
                <p class="fw-semibold mb-1"><a href="{{ route('suppliers.show', $supplierInvoice->supplier) }}" class="text-decoration-none">{{ $supplierInvoice->supplier?->name ?? '—' }}</a></p>
                <p class="text-muted small mb-1">{{ $supplierInvoice->supplier?->code ?? '—' }}</p>
                <p class="text-muted small mb-0">{{ $supplierInvoice->supplier?->phone ?? '—' }}</p>
            </div></div>

            <div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">ملخص السداد</h5></div><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3"><span class="text-muted">حالة السداد</span><span class="badge bg-{{ $supplierInvoice->payment_status_badge_class }}">{{ $supplierInvoice->payment_status_label }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">إجمالي الفاتورة</span><strong>{{ number_format((float) $supplierInvoice->total, 2) }} ج.م</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">مدفوع</span><strong class="text-success">{{ number_format((float) $supplierInvoice->paid_amount, 2) }} ج.م</strong></div>
                <div class="d-flex justify-content-between pt-2 border-top"><span class="text-muted">المتبقي</span><strong class="{{ $supplierInvoice->remaining_amount > 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $supplierInvoice->remaining_amount, 2) }} ج.م</strong></div>
                @if($supplierInvoice->remaining_amount > 0 && $supplierInvoice->status !== 'cancelled')<div class="mt-3"><a href="{{ route('supplier-payments.create', ['supplier_id' => $supplierInvoice->supplier_id, 'supplier_invoice_id' => $supplierInvoice->id, 'amount' => $supplierInvoice->remaining_amount]) }}" class="btn btn-success w-100"><i class="bi bi-cash-stack me-1"></i> تسجيل دفعة</a></div>@endif
            </div></div>

            <div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">بيانات الفاتورة</h5></div><div class="card-body">
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">مرجع المورد</span><strong>{{ $supplierInvoice->supplier_reference ?: '—' }}</strong></div>
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">تاريخ الاستحقاق</span><strong>{{ $supplierInvoice->due_date?->format('Y-m-d') ?: '—' }}</strong></div>
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">أمر الشراء</span><strong>@if($supplierInvoice->purchaseOrder)<a href="{{ route('purchase-orders.show', $supplierInvoice->purchaseOrder) }}">{{ $supplierInvoice->purchaseOrder->purchase_order_number }}</a>@else — @endif</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">إذن الاستلام</span><strong>@if($supplierInvoice->goodsReceipt)<a href="{{ route('goods-receipts.show', $supplierInvoice->goodsReceipt) }}">{{ $supplierInvoice->goodsReceipt->receipt_number }}</a>@else — @endif</strong></div>
            </div></div>
            @if($supplierInvoice->notes)<div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">ملاحظات</h5></div><div class="card-body"><p class="mb-0">{{ $supplierInvoice->notes }}</p></div></div>@endif
        </div>

        <div class="col-lg-8">
            @if($supplierInvoice->items->isNotEmpty())
                <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">بنود الخامات</h5></div><div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>الخامة</th><th>الوحدة</th><th class="text-end">الكمية</th><th class="text-end">تكلفة الوحدة</th><th class="text-end">الإجمالي</th></tr></thead>
                    <tbody>@foreach($supplierInvoice->items as $item)<tr><td><strong>{{ $item->material?->name }}</strong><div class="small text-muted">{{ $item->material?->code }}</div></td><td>{{ $item->material?->unit?->label() }}</td><td class="text-end">{{ number_format((float) $item->quantity, 3) }}</td><td class="text-end">{{ number_format((float) $item->unit_cost, 2) }}</td><td class="text-end fw-semibold">{{ number_format((float) $item->line_total, 2) }} ج.م</td></tr>@endforeach</tbody>
                </table></div></div>
            @endif

            <div class="card {{ $supplierInvoice->items->isNotEmpty() ? 'mt-4' : '' }}"><div class="card-header bg-white"><h5 class="card-title mb-0">إجماليات الفاتورة</h5></div><div class="card-body">
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">الإجمالي الفرعي</span><strong>{{ number_format((float) $supplierInvoice->subtotal, 2) }} ج.م</strong></div>
                @if($supplierInvoice->discount > 0)<div class="d-flex justify-content-between mb-3"><span class="text-muted">الخصم</span><strong>{{ number_format((float) $supplierInvoice->discount, 2) }} ج.م</strong></div>@endif
                @if($supplierInvoice->tax > 0)<div class="d-flex justify-content-between mb-3"><span class="text-muted">الضريبة</span><strong>{{ number_format((float) $supplierInvoice->tax, 2) }} ج.م</strong></div>@endif
                <div class="d-flex justify-content-between pt-3 border-top"><span class="fs-5 fw-semibold">إجمالي الفاتورة</span><strong class="fs-4 text-primary">{{ number_format((float) $supplierInvoice->total, 2) }} ج.م</strong></div>
            </div></div>

            <div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">سجل المدفوعات</h5></div>
                @if($relatedPayments->isNotEmpty())<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الدفعة</th><th>التاريخ</th><th>الطريقة</th><th>الحالة</th><th class="text-end">المبلغ الموزع</th></tr></thead><tbody>
                    @foreach($relatedPayments as $payment)
                        @php $allocated = $payment->allocations->firstWhere('supplier_invoice_id', $supplierInvoice->id)?->amount ?? ($payment->supplier_invoice_id === $supplierInvoice->id ? $payment->amount : 0); @endphp
                        <tr><td><a href="{{ route('supplier-payments.show-payment', $payment) }}" class="fw-semibold text-decoration-none">{{ $payment->payment_number }}</a></td><td>{{ $payment->payment_date?->format('Y-m-d') }}</td><td>{{ \App\Support\ArabicLabels::paymentMethod($payment->payment_method) }}</td><td><span class="badge bg-{{ $payment->status->badgeClass() }}">{{ $payment->status->label() }}</span></td><td class="text-end fw-semibold">{{ number_format((float) $allocated, 2) }} ج.م</td></tr>
                    @endforeach
                </tbody></table></div>@else<div class="text-center text-muted py-5">لم تُسجل مدفوعات لهذه الفاتورة بعد.</div>@endif
            </div>
        </div>
    </div>

    <div class="mt-4"><a href="{{ route('supplier-invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> العودة إلى فواتير الموردين</a></div>
@endsection
