@extends('layouts.dashboard')

@section('title', 'التصنيفات - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">التصنيفات</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">التصنيفات</h1>
            <p class="text-muted mb-0">تنظيم موديلات العبايات داخل تصنيفات المنتجات</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء تصنيف
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('partials.list-filters', [
                'action' => route('categories.index'),
                'placeholder' => 'ابحث بالكود أو الاسم...',
                'statusEnum' => \App\Enums\CategoryStatus::class,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <a href="{{ route('categories.show', $category) }}" class="text-decoration-none fw-semibold">
                                        {{ $category->code }}
                                    </a>
                                </td>
                                <td>{{ $category->name }}</td>
                                <td>
                                    <span class="badge bg-{{ $category->status->badgeClass() }}">
                                        {{ $category->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('categories.show', $category) }}" class="btn btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('categories.edit', $category) }}" class="btn btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف هذا التصنيف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-tags display-6 d-block mb-2"></i>
                                    لا توجد تصنيفات.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $categories->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
