@extends('layouts.dashboard')

@section('title', $category->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}" class="text-decoration-none">التصنيفات</a></li>
            <li class="breadcrumb-item active">{{ $category->code }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $category->name }}</h1>
                <span class="badge bg-{{ $category->status->badgeClass() }}">{{ $category->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $category->code }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('categories.edit', $category) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> تعديل</a>
            <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('هل تريد حذف هذا التصنيف؟')">
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
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">الوصف</label>
                        <p class="mb-0">{{ $category->description ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">الملخص</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">الموديلات</label>
                        <p class="mb-0 fw-semibold fs-5">{{ $category->products_count }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">تاريخ الإنشاء</label>
                        <p class="mb-0 small">{{ $category->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div>
                        <label class="form-label text-muted small mb-1">آخر تحديث</label>
                        <p class="mb-0 small">{{ $category->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> العودة إلى التصنيفات</a>
    </div>
@endsection
