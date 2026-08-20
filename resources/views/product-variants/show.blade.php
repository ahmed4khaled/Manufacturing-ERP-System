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

    <div class="mt-3"><a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">العودة إلى المتغيرات</a></div>
@endsection
