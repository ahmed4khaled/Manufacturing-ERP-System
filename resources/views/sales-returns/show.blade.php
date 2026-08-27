@extends('layouts.dashboard')

@section('title', $salesReturn->return_number . ' - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2"><h1 class="h3 mb-0">{{ $salesReturn->return_number }}</h1><span class="badge bg-{{ $salesReturn->status->badgeClass() }}">{{ $salesReturn->status->label() }}</span></div>
            <p class="text-muted mb-0 mt-1">الفاتورة {{ $salesReturn->salesInvoice->invoice_number }} · {{ $salesReturn->customer->name }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('sales-returns.print', $salesReturn) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer me-1"></i> طباعة</a>
            @if ($salesReturn->status === \App\Enums\SalesReturnStatus::DRAFT)
                <a href="{{ route('sales-returns.edit', $salesReturn) }}" class="btn btn-primary">تعديل</a>
                <form method="POST" action="{{ route('sales-returns.confirm', $salesReturn) }}" onsubmit="return confirm('هل تريد تأكيد المرتجع وترحيل أثره المالي والمخزني؟')">@csrf<button class="btn btn-success">تأكيد وترحيل</button></form>
                <form method="POST" action="{{ route('sales-returns.destroy', $salesReturn) }}" onsubmit="return confirm('هل تريد حذف مسودة المرتجع؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف</button></form>
            @elseif ($salesReturn->status === \App\Enums\SalesReturnStatus::CONFIRMED)
                <form method="POST" action="{{ route('sales-returns.cancel', $salesReturn) }}" onsubmit="return confirm('هل تريد إلغاء المرتجع وعكس أثره؟')">@csrf<button class="btn btn-outline-danger">إلغاء المرتجع</button></form>
            @elseif ($salesReturn->status === \App\Enums\SalesReturnStatus::CANCELLED)
                <form method="POST" action="{{ route('sales-returns.destroy', $salesReturn) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟ سيتم إخفاؤه من السجلات النشطة مع الاحتفاظ بتاريخه.')">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف</button></form>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">تاريخ المرتجع</div><div class="fw-semibold">{{ $salesReturn->return_date->format('Y-m-d') }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">إجمالي الكمية</div><div class="fw-semibold">{{ number_format($salesReturn->total_quantity, 3) }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">قيمة المرتجع</div><div class="fw-semibold text-primary">{{ number_format($salesReturn->total_amount, 2) }} ج.م</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">أنشأه</div><div class="fw-semibold">{{ $salesReturn->creator?->name ?? '—' }}</div></div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white"><h5 class="card-title mb-0">البنود المرتجعة</h5></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0"><thead class="table-light"><tr><th>الصنف</th><th>اللون</th><th>المقاس</th><th>رمز المخزون</th><th class="text-end">الكمية</th><th class="text-end">السعر</th><th class="text-end">الإجمالي</th></tr></thead>
                <tbody>@foreach($salesReturn->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->color_name ?? '—' }}</td><td>{{ $item->size_name ?? '—' }}</td><td><code>{{ $item->sku }}</code></td><td class="text-end">{{ number_format($item->quantity, 3) }}</td><td class="text-end">{{ number_format($item->unit_price, 2) }}</td><td class="text-end fw-semibold">{{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </div>

    <div class="card"><div class="card-body"><div class="mb-2"><span class="text-muted">سبب المرتجع:</span> {{ $salesReturn->reason }}</div>@if($salesReturn->notes)<div><span class="text-muted">ملاحظات:</span> {{ $salesReturn->notes }}</div>@endif</div></div>
@endsection
