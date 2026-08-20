@extends('layouts.dashboard')

@section('title', 'الخامات - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">الخامات</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">الخامات</h1>
            <p class="text-muted mb-0">الخامات المستخدمة في إنتاج المصنع</p>
        </div>
        <a href="{{ route('materials.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء خامة
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input name="search" class="form-control" value="{{ request('search') }}" placeholder="ابحث بالكود أو الاسم...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">كل التصنيفات</option>
                        @foreach (\App\Enums\MaterialCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">كل الحالات</option>
                        @foreach (\App\Enums\MaterialStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">تصفية</button>
                    @if(request()->hasAny(['search', 'category', 'status']))
                        <a href="{{ route('materials.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>التصنيف</th>
                            <th>الوحدة</th>
                            <th class="text-end">الحد الأدنى للمخزون</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($materials as $material)
                            <tr>
                                <td>
                                    <a href="{{ route('materials.show', $material) }}" class="text-decoration-none fw-semibold">
                                        {{ $material->code }}
                                    </a>
                                </td>
                                <td>{{ $material->name }}</td>
                                <td>{{ $material->category->label() }}</td>
                                <td>{{ $material->unit->label() }}</td>
                                <td class="text-end">{{ $material->minimum_stock }}</td>
                                <td>
                                    <span class="badge bg-{{ $material->status->badgeClass() }}">
                                        {{ $material->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('materials.show', $material) }}" class="btn btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('materials.edit', $material) }}" class="btn btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('materials.destroy', $material) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف هذه الخامة؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-box-seam display-6 d-block mb-2"></i>
                                    لا توجد خامات.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($materials->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $materials->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
