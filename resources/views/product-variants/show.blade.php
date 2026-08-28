@extends('layouts.dashboard')

@section('title', $productVariant->sku)

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $productVariant->sku }}</h1>
                <span class="badge bg-{{ $productVariant->status->badgeClass() }}">{{ $productVariant->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $productVariant->product?->name }} · {{ $productVariant->color?->name }} · {{ $productVariant->size?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('product-variants.edit', $productVariant) }}" class="btn btn-primary">تعديل</a>
            <form action="{{ route('product-variants.destroy', $productVariant) }}" method="POST" onsubmit="return confirm('هل تريد الحذف؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف</button></form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">تفاصيل المتغير</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="text-muted small">المنتج</label><p class="fw-semibold mb-0"><a href="{{ route('products.show', $productVariant->product) }}">{{ $productVariant->product?->code }} — {{ $productVariant->product?->name }}</a></p></div>
                        <div class="col-md-6"><label class="text-muted small">الباركود</label><p class="fw-semibold mb-0">{{ $productVariant->barcode ?? '—' }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">اللون</label><p class="fw-semibold mb-0">{{ $productVariant->color?->name }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">المقاس</label><p class="fw-semibold mb-0">{{ $productVariant->size?->name }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">السعر الافتراضي</label><p class="fw-semibold mb-0">{{ number_format($productVariant->default_price, 2) }} ج.م</p></div>
                        <div class="col-md-4"><label class="text-muted small">التكلفة القياسية</label><p class="fw-semibold mb-0">{{ $productVariant->standard_cost !== null ? number_format($productVariant->standard_cost, 2).' ج.م' : '—' }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">آخر تكلفة إنتاج فعلية</label><p class="fw-semibold mb-0">{{ $productVariant->last_production_cost !== null ? number_format($productVariant->last_production_cost, 4).' ج.م' : 'غير مُقيّمة' }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">تكلفة المتغير الحالية</label><p class="fw-semibold text-primary mb-0">{{ $productVariant->actual_cost !== null ? number_format($productVariant->actual_cost, 4).' ج.م' : 'غير مُقيّمة' }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">الرصيد الحالي</label><p class="fw-semibold mb-0"><a href="{{ route('inventory.show', $productVariant) }}" class="text-decoration-none">عرض المخزون</a></p></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <p class="small text-muted mb-1">تاريخ الإنشاء</p><p>{{ $productVariant->created_at->format('Y-m-d H:i') }}</p>
                <p class="small text-muted mb-1">آخر تحديث</p><p class="mb-0">{{ $productVariant->updated_at->format('Y-m-d H:i') }}</p>
            </div></div>
        </div>
    </div>

    @can('inventory.view')
        <div class="card mt-4" id="fifo-costing">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong>تكلفة متغير المنتج وطبقات FIFO</strong>
                <a href="{{ route('inventory.cost-layers.index', ['product_variant_id' => $productVariant->id]) }}" class="btn btn-sm btn-outline-primary">عرض صفحة طبقات المنتجات</a>
            </div>
            <div class="card-body border-bottom"><div class="row g-3 text-center">
                <div class="col-6 col-lg"><small class="text-muted d-block">التكلفة القياسية</small><strong>{{ $productVariant->standard_cost !== null ? number_format($productVariant->standard_cost, 4).' ج.م' : '—' }}</strong></div>
                <div class="col-6 col-lg"><small class="text-muted d-block">آخر تكلفة إنتاج</small><strong>{{ $productVariant->last_production_cost !== null ? number_format($productVariant->last_production_cost, 4).' ج.م' : 'غير مُقيّمة' }}</strong></div>
                <div class="col-6 col-lg"><small class="text-muted d-block">التكلفة الفعلية</small><strong>{{ $productVariant->actual_cost !== null ? number_format($productVariant->actual_cost, 4).' ج.م' : 'غير مُقيّمة' }}</strong></div>
                <div class="col-6 col-lg"><small class="text-muted d-block">قيمة مخزون FIFO الحالية</small><strong>{{ number_format($fifoInventoryValue, 2) }} ج.م</strong></div>
                <div class="col-6 col-lg"><small class="text-muted d-block">الكمية المتاحة</small><strong>{{ number_format($availableQuantity, 3) }}</strong></div>
            </div></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>الطبقة</th><th>التاريخ</th><th>أمر الإنتاج / المصدر</th><th>الإنتاج</th><th class="text-end">الكمية الأصلية</th><th class="text-end">المتبقي</th><th class="text-end">تكلفة الوحدة</th><th>الحالة</th></tr></thead><tbody>
                @forelse($productVariant->costLayers as $layer)
                    @php
                        $layerStatus = !$layer->is_final ? ['مؤقتة', 'info'] : ($layer->unit_cost === null ? ['غير مُقيّمة', 'warning'] : ((float) $layer->remaining_quantity > 0 ? ['متاحة', 'success'] : ['مستنفدة', 'secondary']));
                    @endphp
                    <tr><td>#{{ $layer->id }}</td><td>{{ $layer->layer_date?->format('Y-m-d') }}</td><td>@if($layer->productionOrder)<a href="{{ route('production-orders.show', $layer->productionOrder) }}">{{ $layer->productionOrder->order_number }}</a>@elseرصيد قديم / تسوية@endif</td><td>{{ $layer->productionOutput ? '#'.$layer->productionOutput->id : '—' }}</td><td class="text-end">{{ number_format($layer->original_quantity, 3) }}</td><td class="text-end">{{ number_format($layer->remaining_quantity, 3) }}</td><td class="text-end">{{ $layer->unit_cost !== null ? number_format($layer->unit_cost, 4).' ج.م' : '—' }}</td><td><span class="badge bg-{{ $layerStatus[1] }}">{{ $layerStatus[0] }}</span></td></tr>
                @empty<tr><td colspan="8" class="text-center text-muted">لا توجد طبقات تكلفة مسجلة.</td></tr>@endforelse
            </tbody></table></div>
            @if($productVariant->costLayers->where('is_final', true)->whereNull('unit_cost')->isNotEmpty())<div class="card-footer text-warning">يوجد رصيد بلا تكلفة موثوقة؛ لا يدخل في قيمة المخزون المُقيّمة.</div>@endif
        </div>
    @endcan

    <div class="mt-3"><a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">العودة إلى المتغيرات</a></div>
@endsection
