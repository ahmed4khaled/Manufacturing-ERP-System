@extends('layouts.dashboard')
@section('title', 'أوامر الشراء')
@section('content')
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">أوامر الشراء</h1>
            <p class="text-muted mb-0">الطلبات المسجلة لدى الموردين</p>
        </div><a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>إنشاء أمر شراء</a>
    </div>
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('purchase-orders.index') }}" class="mb-4">
    <div class="row g-2 align-items-end">

        <div class="col-md-4">
            <label class="form-label">تصفية حسب الحالة</label>

            <select name="status" class="form-select">
                <option value="">كل أوامر الشراء</option>

                <option value="draft"
                    {{ request('status') === 'draft' ? 'selected' : '' }}>
                    مسودة
                </option>

                <option value="approved"
                    {{ request('status') === 'approved' ? 'selected' : '' }}>
                    معتمد
                </option>

                <option value="partially_received"
                    {{ request('status') === 'partially_received' ? 'selected' : '' }}>
                    مستلم جزئيًا
                </option>

                <option value="fully_received"
                    {{ request('status') === 'fully_received' ? 'selected' : '' }}>
                    مستلم بالكامل
                </option>

                <option value="cancelled"
                    {{ request('status') === 'cancelled' ? 'selected' : '' }}>
                    ملغي
                </option>
            </select>
        </div>

        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel me-1"></i>
                تصفية
            </button>

            <a href="{{ route('purchase-orders.index') }}"
               class="btn btn-outline-secondary">
                إعادة تعيين
            </a>
        </div>

    </div>
</form>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>المورد</th>
                            <th>تاريخ الطلب</th>
                            <th>التسليم المتوقع</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td><a href="{{ route('purchase-orders.show', $order) }}"
                                        class="fw-semibold text-decoration-none">{{ $order->purchase_order_number }}</a>
                                </td>
                                <td>{{ $order->supplier->name }}</td>
                                <td>{{ $order->order_date->format('Y-m-d') }}</td>
                                <td>{{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</td>
                                <td><span
                                        class="badge bg-secondary">{{ \App\Support\ArabicLabels::status($order->status) }}</span>
                                </td>
                                <td class="text-end">{{ number_format($order->total, 2) }}</td>
                        </tr>@empty<tr>
                                <td colspan="6" class="text-center text-muted py-5">لا توجد أوامر شراء حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>{{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
