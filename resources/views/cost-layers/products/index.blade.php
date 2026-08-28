@extends('layouts.dashboard')

@section('title', 'طبقات تكلفة المنتجات التامة FIFO')

@section('breadcrumb')
    <ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">مخزون المنتجات</a></li><li class="breadcrumb-item active">طبقات التكلفة FIFO</li></ol>
@endsection

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">طبقات تكلفة المنتجات التامة FIFO</h1><p class="text-muted mb-0">عرض رقابي لطبقات الإنتاج والمخزون المسجلة، بدون تعديل.</p></div>
    <div class="card mb-4"><div class="card-body"><form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6"><label class="form-label">متغير المنتج</label><select name="product_variant_id" class="form-select"><option value="">كل المتغيرات</option>@foreach($variants as $variant)<option value="{{ $variant->id }}" @selected((string) request('product_variant_id') === (string) $variant->id)>{{ $variant->sku }} — {{ $variant->product?->name }} / {{ $variant->color?->name }} / {{ $variant->size?->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="">كل الحالات</option><option value="available" @selected(request('status') === 'available')>متاحة</option><option value="depleted" @selected(request('status') === 'depleted')>مستنفدة</option><option value="unvalued" @selected(request('status') === 'unvalued')>غير مُقيّمة</option><option value="provisional" @selected(request('status') === 'provisional')>مؤقتة</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">تصفية</button><a href="{{ route('inventory.cost-layers.index') }}" class="btn btn-outline-secondary">مسح</a></div>
    </form></div></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>متغير المنتج</th><th>أمر الإنتاج</th><th>الإنتاج</th><th class="text-end">الكمية الأصلية</th><th class="text-end">المتبقي</th><th class="text-end">تكلفة الوحدة</th><th class="text-end">إجمالي التكلفة</th><th>التاريخ</th><th>الحالة</th></tr></thead>
        <tbody>@forelse($layers as $layer)
            @php
                $status = !$layer->is_final ? ['مؤقتة', 'info'] : ($layer->unit_cost === null ? ['غير مُقيّمة', 'warning'] : ((float) $layer->remaining_quantity > 0 ? ['متاحة', 'success'] : ['مستنفدة', 'secondary']));
            @endphp
            <tr>
                <td><a href="{{ route('product-variants.show', $layer->productVariant) }}"><strong>{{ $layer->productVariant?->sku }}</strong></a><div class="small text-muted">{{ $layer->productVariant?->product?->name }} / {{ $layer->productVariant?->color?->name }} / {{ $layer->productVariant?->size?->name }}</div></td>
                <td>@if($layer->productionOrder)<a href="{{ route('production-orders.show', $layer->productionOrder) }}">{{ $layer->productionOrder->order_number }}</a>@else<span class="text-muted">رصيد قديم / تسوية</span>@endif</td>
                <td>{{ $layer->productionOutput ? '#'.$layer->productionOutput->id : '—' }}</td>
                <td class="text-end">{{ number_format($layer->original_quantity, 3) }}</td><td class="text-end fw-semibold">{{ number_format($layer->remaining_quantity, 3) }}</td>
                <td class="text-end">{{ $layer->unit_cost !== null ? number_format($layer->unit_cost, 4).' ج.م' : '—' }}</td><td class="text-end">{{ $layer->total_original_cost !== null ? number_format($layer->total_original_cost, 2).' ج.م' : '—' }}</td>
                <td>{{ $layer->layer_date?->format('Y-m-d') }}</td><td><span class="badge bg-{{ $status[1] }}">{{ $status[0] }}</span></td>
            </tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-4">لا توجد طبقات تكلفة مطابقة.</td></tr>@endforelse</tbody>
    </table></div>@if($layers->hasPages())<div class="card-footer bg-white">{{ $layers->links() }}</div>@endif</div>
@endsection
