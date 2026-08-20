@extends('layouts.dashboard')

@section('title', 'سندات القبض - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">سندات القبض</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">سندات القبض</h1>
            <p class="text-muted mb-0">تسجيل المبالغ المستلمة من العملاء</p>
        </div>
        <a href="{{ route('payment-receipts.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء سند
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('payment-receipts.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="رقم السند أو العميل..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="customer_id" class="form-select">
                        <option value="">كل العملاء</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" placeholder="من" value="{{ request('date_from') }}" title="من تاريخ">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" placeholder="إلى" value="{{ request('date_to') }}" title="إلى تاريخ">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">كل الحالات</option>
                        @foreach (\App\Enums\PaymentReceiptStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    @if (request()->hasAny(['search', 'status', 'customer_id', 'date_from', 'date_to']))
                        <a href="{{ route('payment-receipts.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم السند</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th class="text-end">المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>الحالة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr>
                                
                                <td>

                                    <a href="{{ route('payment-receipts.show', $receipt) }}" class="text-decoration-none fw-semibold">
                                        {{ $receipt->receipt_number }}
                                    </a>
                                </td>
                                <td>{{ $receipt->customer?->name }}</td>
                                <td>{{ $receipt->payment_date->format('Y-m-d') }}</td>
                                <td class="text-end">{{ number_format($receipt->amount, 2) }} ج.م</td>
                                <td>{{ $receipt->payment_method->label() }}</td>
                                <td><span class="badge bg-{{ $receipt->status->badgeClass() }}">{{ $receipt->status->label() }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                         <a
                                            href="{{ route(
                                               'payment-receipts.print', $receipt
                                            ) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-dark"
                                            title="طباعة الفاتورة"
                                        >
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <a href="{{ route('payment-receipts.show', $receipt) }}" class="btn btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                                        @if ($receipt->status === \App\Enums\PaymentReceiptStatus::DRAFT)
                                            <a href="{{ route('payment-receipts.edit', $receipt) }}" class="btn btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                            <form action="{{ route('payment-receipts.destroy', $receipt) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف مسودة السند؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-cash-coin display-6 d-block mb-2"></i>
                                    لا توجد سندات قبض.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($receipts->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $receipts->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
