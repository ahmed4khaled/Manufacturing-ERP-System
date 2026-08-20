@extends('layouts.dashboard')

@section('title', 'الموردون - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">الموردون</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">الموردون</h1>
            <p class="text-muted mb-0">إدارة بيانات الموردين وشركاء التوريد</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء مورد
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('suppliers.index') }}" class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="ابحث بالكود أو الاسم أو الهاتف أو البريد..."
                            value="{{ request('search') }}"
                        >
                        @if (request('search'))
                            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
                        @endif
                        <button type="submit" class="btn btn-primary">بحث</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>البريد الإلكتروني</th>
                            <th>الحالة</th>
                            <th>الرقم الضريبي</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td><a class="fw-semibold text-decoration-none" href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->code }}</a></td>
                                <td>{{ $supplier->name }}</td>
                                <td>{{ $supplier->phone ?: '—' }}</td>
                                <td>{{ $supplier->email ?: '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $supplier->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ \App\Support\ArabicLabels::status($supplier->status) }}
                                    </span>
                                </td>
                                <td>{{ $supplier->tax_number ?: '—' }}</td>
                                <td class="text-end"><div class="btn-group btn-group-sm"><a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a><a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a></div></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-truck display-6 d-block mb-2"></i>
                                    لا يوجد موردون.
                                    @if (request('search'))
                                        جرّب عبارة بحث مختلفة.
                                    @else
                                        <a href="{{ route('suppliers.create') }}">أنشئ أول مورد</a>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $suppliers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
