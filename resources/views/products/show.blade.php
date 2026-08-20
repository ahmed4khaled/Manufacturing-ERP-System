@extends('layouts.dashboard')

@section('title', $product->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none">الموديلات</a></li>
            <li class="breadcrumb-item active">{{ $product->code }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $product->name }}</h1>
                <span class="badge bg-{{ $product->status->badgeClass() }}">{{ $product->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $product->code }} · {{ $product->category?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('product-variants.create', ['product_id' => $product->id]) }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> إضافة متغير</a>
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> تعديل</a>
            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('هل تريد حذف هذا الموديل؟')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> حذف</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">التفاصيل</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">الوصف</label>
                            <p class="mb-0">{{ $product->description ?? '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">سعر التكلفة</label>
                            <p class="mb-0 fw-semibold">{{ number_format($product->cost_price, 2) }} ج.م</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">المتغيرات</h5>
                    <span class="badge bg-secondary">{{ $product->variants->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if ($product->variants->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">لا توجد متغيرات حتى الآن. <a href="{{ route('product-variants.create') }}">إنشاء سجل جديد</a>.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>رمز المخزون</th>
                                        <th>اللون</th>
                                        <th>المقاس</th>
                                        <th>الحالة</th>
                                        <th class="text-end">السعر</th>
                                        <th class="text-end">الكمية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->variants as $variant)
                                        <tr>
                                            <td><a href="{{ route('product-variants.show', $variant) }}" class="text-decoration-none">{{ $variant->sku }}</a></td>
                                            <td>
                                                @if ($variant->color?->hex_code)
                                                    <span class="d-inline-block rounded-circle border" style="width:14px;height:14px;background:{{ $variant->color->hex_code }}"></span>
                                                @endif
                                                {{ $variant->color?->name }}
                                            </td>
                                            <td>{{ $variant->size?->name }}</td>
                                            <td><span class="badge bg-{{ $variant->status->badgeClass() }}">{{ $variant->status->label() }}</span></td>
                                            <td class="text-end">{{ number_format($variant->default_price, 2) }}</td>
                                            <td class="text-end">{{ $variant->quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">الملخص</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">التصنيف</label>
                        <p class="mb-0 fw-semibold">{{ $product->category?->name ?? '—' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">تاريخ الإنشاء</label>
                        <p class="mb-0 small">{{ $product->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div>
                        <label class="form-label text-muted small mb-1">آخر تحديث</label>
                        <p class="mb-0 small">{{ $product->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> العودة إلى المنتجات</a>
    </div>
@endsection
