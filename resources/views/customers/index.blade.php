@extends('layouts.dashboard')

@section('title', 'العملاء - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">العملاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">العملاء</h1>
            <p class="text-muted mb-0">إدارة حسابات العملاء وحدود الائتمان</p>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء عميل
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('customers.index') }}" class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="ابحث بالكود أو الاسم أو الهاتف أو المدينة..."
                            value="{{ request('search') }}"
                        >
                        @if (request('search'))
                            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
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
                            <th>المدينة</th>
                            <th>الحالة</th>
                            <th class="text-end">حد الائتمان</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none fw-semibold">
                                        {{ $customer->code }}
                                    </a>
                                </td>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ $customer->city ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $customer->status->badgeClass() }}">
                                        {{ $customer->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format($customer->credit_limit, 2) }} ج.م</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟ سيتم إخفاؤه من السجلات النشطة مع الاحتفاظ بتاريخه.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-people display-6 d-block mb-2"></i>
                                    لا يوجد عملاء.
                                    @if (request('search'))
                                        جرّب عبارة بحث مختلفة.
                                    @else
                                        <a href="{{ route('customers.create') }}">أنشئ أول عميل</a>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $customers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
