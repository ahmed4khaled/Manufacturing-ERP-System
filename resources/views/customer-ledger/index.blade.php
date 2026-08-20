@extends('layouts.dashboard')

@section('title', 'كشف حساب العملاء - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">كشف حساب العملاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">كشف حساب العملاء</h1>
        <p class="text-muted mb-0">حسابات العملاء المدينة — جميع الحركات المالية للعملاء</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('customer-ledger.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="المرجع أو العميل..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select">
                        <option value="">كل العملاء</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="من">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="إلى">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    @if (request()->hasAny(['search', 'customer_id', 'date_from', 'date_to']))
                        <a href="{{ route('customer-ledger.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>العميل</th>
                            <th>المرجع</th>
                            <th>الوصف</th>
                            <th class="text-end">مدين</th>
                            <th class="text-end">دائن</th>
                            <th class="text-end">الرصيد التراكمي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->transaction_date->format('Y-m-d') }}</td>
                                <td>
                                    <a href="{{ route('customers.show', $entry->customer) }}" class="text-decoration-none">
                                        {{ $entry->customer?->name }}
                                    </a>
                                </td>
                                <td>
                                    <code>{{ $entry->reference_number ?? '—' }}</code>
                                    <br><small class="text-muted">{{ $entry->reference_type->label() }}</small>
                                </td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                                <td class="text-end">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($entry->running_balance ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-text display-6 d-block mb-2"></i>
                                    لا توجد قيود حساب.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entries->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $entries->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
