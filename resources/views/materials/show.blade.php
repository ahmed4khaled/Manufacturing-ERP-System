@extends('layouts.dashboard')

@section('title', $material->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('materials.index') }}" class="text-decoration-none">الخامات</a></li>
            <li class="breadcrumb-item active">{{ $material->code }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $material->name }}</h1>
                <span class="badge bg-{{ $material->status->badgeClass() }}">{{ $material->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $material->code }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('material-inventory.show', $material) }}" class="btn btn-outline-secondary"><i class="bi bi-boxes me-1"></i> المخزون</a>
            <a href="{{ route('materials.edit', $material) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> تعديل</a>
            <form action="{{ route('materials.destroy', $material) }}" method="POST" onsubmit="return confirm('هل تريد حذف هذه الخامة؟')">
                @csrf
                @method('DELETE')
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
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">التصنيف</label>
                            <p class="mb-0">{{ $material->category->label() }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">وحدة القياس</label>
                            <p class="mb-0">{{ $material->unit->label() }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">الحد الأدنى / حد إعادة الطلب</label>
                            <p class="mb-0">{{ $material->minimum_stock }} {{ $material->unit->label() }}</p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">الوصف</label>
                            <p class="mb-0">{{ $material->description ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">الملخص</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">الرصيد الحالي</label>
                        <p class="mb-0 fw-semibold fs-5">{{ $material->quantity }} {{ $material->unit->label() }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">تاريخ الإنشاء</label>
                        <p class="mb-0 small">{{ $material->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div>
                        <label class="form-label text-muted small mb-1">آخر تحديث</label>
                        <p class="mb-0 small">{{ $material->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('materials.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> العودة إلى الخامات</a>
    </div>
@endsection
