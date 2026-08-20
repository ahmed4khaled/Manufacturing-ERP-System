@extends('layouts.dashboard')

@section('title', 'مخزون الخامات - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">مخزون الخامات</h1>
            <p class="text-muted mb-0">الرصيد الحالي حسب الخامة</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('material-inventory.opening-stock.create') }}" class="btn btn-outline-primary">الرصيد الافتتاحي</a>
            <a href="{{ route('material-inventory.adjustments.create') }}" class="btn btn-primary">تسوية المخزون</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input name="search" class="form-control" value="{{ request('search') }}" placeholder="الكود أو الاسم...">
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
                        <a href="{{ route('material-inventory.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
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
                            <th class="text-end">الرصيد الحالي</th>
                            <th class="text-end">الحد الأدنى</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            @php
                                $isLowStock = bccomp($material->current_stock, (string) $material->minimum_stock, 3) < 0;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('material-inventory.show', $material) }}" class="text-decoration-none fw-semibold">
                                        {{ $material->code }}
                                    </a>
                                </td>
                                <td>{{ $material->name }}</td>
                                <td>{{ $material->category->label() }}</td>
                                <td>{{ $material->unit->label() }}</td>
                                <td class="text-end fw-semibold {{ $isLowStock ? 'text-danger' : '' }}">
                                    {{ $material->current_stock }}
                                </td>
                                <td class="text-end">{{ $material->minimum_stock }}</td>
                                <td>
                                    @if($isLowStock)
                                        <span class="badge bg-warning text-dark">مخزون منخفض</span>
                                    @elseif(bccomp($material->current_stock, '0', 3) > 0)
                                        <span class="badge bg-success">متوفر</span>
                                    @else
                                        <span class="badge bg-secondary">غير متوفر</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">لا توجد خامات.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($materials->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $materials->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
