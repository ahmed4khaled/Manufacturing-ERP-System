@extends('layouts.dashboard')

@section('title', 'حركات مخزون الخامات - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">حركات مخزون الخامات</h1>
            <p class="text-muted mb-0">سجل حركات مخزون الخامات</p>
        </div>
        <a href="{{ route('material-inventory.index') }}" class="btn btn-outline-secondary">ملخص المخزون</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <input name="search" class="form-control" value="{{ request('search') }}" placeholder="رقم المرجع...">
                </div>
                <div class="col-md-3">
                    <select name="material_id" class="form-select">
                        <option value="">كل الخامات</option>
                        @foreach($materials as $material)
                            <option value="{{ $material->id }}" @selected(request('material_id') == $material->id)>
                                {{ $material->code }} - {{ $material->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="movement_type" class="form-select">
                        <option value="">كل الأنواع</option>
                        @foreach(\App\Enums\MaterialStockMovementType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('movement_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="direction" class="form-select">
                        <option value="">كل الاتجاهات</option>
                        @foreach(\App\Enums\StockMovementDirection::cases() as $direction)
                            <option value="{{ $direction->value }}" @selected(request('direction') === $direction->value)>{{ $direction->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">تصفية</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الخامة</th>
                            <th>التصنيف</th>
                            <th>النوع</th>
                            <th>الاتجاه</th>
                            <th class="text-end">الكمية</th>
                            <th>المرجع</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td>{{ $movement->movement_date->format('Y-m-d') }}</td>
                                <td>
                                    <a href="{{ route('material-inventory.show', $movement->material) }}" class="text-decoration-none">
                                        {{ $movement->material?->code }}
                                    </a>
                                </td>
                                <td>{{ $movement->material?->category?->label() }}</td>
                                <td>{{ $movement->movement_type->label() }}</td>
                                <td>
                                    <span class="badge bg-{{ $movement->direction->badgeClass() }}">
                                        {{ $movement->direction->label() }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $movement->quantity }}</td>
                                <td><code>{{ $movement->reference_number ?? '-' }}</code></td>
                                <td>{{ $movement->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">لا توجد حركات مخزون.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($movements->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $movements->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
