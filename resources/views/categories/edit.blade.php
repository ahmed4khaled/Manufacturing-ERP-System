@extends('layouts.dashboard')

@section('title', 'تعديل ' . $category->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}" class="text-decoration-none">التصنيفات</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.show', $category) }}" class="text-decoration-none">{{ $category->code }}</a></li>
            <li class="breadcrumb-item active">تعديل</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">تعديل التصنيف</h1>
        <p class="text-muted mb-0">{{ $category->code }} — {{ $category->name }}</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">بيانات التصنيف</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('categories.update', $category) }}">
                        @csrf
                        @method('PUT')
                        @include('categories.partials.form', ['category' => $category])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> تحديث التصنيف</button>
                            <a href="{{ route('categories.show', $category) }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
