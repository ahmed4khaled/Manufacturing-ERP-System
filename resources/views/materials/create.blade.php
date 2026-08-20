@extends('layouts.dashboard')

@section('title', 'إنشاء خامة - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('materials.index') }}" class="text-decoration-none">الخامات</a></li>
            <li class="breadcrumb-item active">إنشاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء خامة</h1>
        <p class="text-muted mb-0">إضافة خامة جديدة للإنتاج</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">بيانات الخامة</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('materials.store') }}">
                        @csrf
                        @include('materials.partials.form')
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> حفظ الخامة</button>
                            <a href="{{ route('materials.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> ملاحظة</h6>
                    <p class="text-muted small mb-0">يُنشأ كود الخامة تلقائيًا، وتكون حالة الخامات الجديدة <span class="badge bg-success">نشط</span>. تُدار الأرصدة بصورة مستقلة من خلال مخزون الخامات.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
