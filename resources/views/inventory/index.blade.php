@extends('layouts.dashboard')

@section('title', 'المخزون - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">المخزون</h1><p class="text-muted mb-0">الرصيد الحالي حسب متغير المنتج</p></div>
        <div class="d-flex gap-2"><a href="{{ route('inventory.opening-stock.create') }}" class="btn btn-outline-primary">الرصيد الافتتاحي</a><a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary">تسوية المخزون</a></div>
    </div>
    <div class="card"><div class="card-body">
        <form method="GET" class="row g-3 mb-4"><div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input name="search" class="form-control" value="{{ request('search') }}" placeholder="المنتج أو رمز المخزون أو الباركود أو اللون أو المقاس..."></div></div><div class="col-md-3"><select name="product_id" class="form-select"><option value="">كل المنتجات</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->code }} - {{ $product->name }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-primary">تصفية</button>@if(request()->hasAny(['search','product_id'])) <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>@endif</div></form>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>المنتج</th><th>اللون</th><th>المقاس</th><th>رمز المخزون</th><th>الباركود</th><th class="text-end">الرصيد الحالي</th><th>الحالة</th></tr></thead><tbody>@forelse($variants as $variant)<tr><td>{{ $variant->product?->code }} - {{ $variant->product?->name }}</td><td>{{ $variant->color?->name }}</td><td>{{ $variant->size?->name }}</td><td><a href="{{ route('inventory.show', $variant) }}" class="text-decoration-none fw-semibold">{{ $variant->sku }}</a></td><td>{{ $variant->barcode ?? '-' }}</td><td class="text-end fw-semibold">{{ $variant->current_stock }}</td><td><span class="badge bg-{{ $variant->current_stock > 0 ? 'success' : 'secondary' }}">{{ $variant->current_stock > 0 ? 'متوفر' : 'غير متوفر' }}</span></td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-5">لا توجد متغيرات.</td></tr>@endforelse</tbody></table></div>
        @if($variants->hasPages())<div class="d-flex justify-content-center mt-4">{{ $variants->links('pagination::bootstrap-5') }}</div>@endif
    </div></div>
@endsection
