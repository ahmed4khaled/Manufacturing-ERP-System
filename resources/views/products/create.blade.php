@extends('layouts.dashboard')

@section('title', 'إنشاء موديل - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none">الموديلات</a></li>
            <li class="breadcrumb-item active">إنشاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء موديل</h1>
        <p class="text-muted mb-0">إضافة موديل عباية جديد</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">بيانات الموديل</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('products.store') }}">
                        @csrf
                        @include('products.partials.form', ['categories' => $categories])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> حفظ الموديل</button>
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> ملاحظة</h6>
                    <p class="text-muted small mb-0">يُنشأ الكود تلقائيًا. أضف متغيرات اللون والمقاس بعد إنشاء الموديل.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
