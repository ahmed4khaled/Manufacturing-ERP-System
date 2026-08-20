@extends('layouts.dashboard')

@section('title', 'مرتجعات المبيعات - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">مرتجعات المبيعات</h1><p class="text-muted mb-0">متابعة المرتجعات المرتبطة بفواتير المبيعات.</p></div>
        <a href="{{ route('sales-returns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> مرتجع جديد</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="رقم المرتجع أو الفاتورة أو العميل..."></div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select"><option value="">كل العملاء</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>@endforeach</select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select"><option value="">كل الحالات</option>@foreach(\App\Enums\SalesReturnStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                </div>
                <div class="col-auto"><button class="btn btn-outline-primary">تصفية</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>رقم المرتجع</th><th>الفاتورة</th><th>العميل</th><th>التاريخ</th><th>الحالة</th><th class="text-end">الكمية</th><th class="text-end">القيمة</th></tr></thead>
                <tbody>
                    @forelse ($salesReturns as $salesReturn)
                        <tr>
                            <td><a href="{{ route('sales-returns.show', $salesReturn) }}" class="fw-semibold text-decoration-none">{{ $salesReturn->return_number }}</a></td>
                            <td><a href="{{ route('sales-invoices.show', $salesReturn->salesInvoice) }}" class="text-decoration-none">{{ $salesReturn->salesInvoice->invoice_number }}</a></td>
                            <td>{{ $salesReturn->customer->name }}</td>
                            <td>{{ $salesReturn->return_date->format('Y-m-d') }}</td>
                            <td><span class="badge bg-{{ $salesReturn->status->badgeClass() }}">{{ $salesReturn->status->label() }}</span></td>
                            <td class="text-end">{{ number_format($salesReturn->total_quantity, 3) }}</td>
                            <td class="text-end">{{ number_format($salesReturn->total_amount, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">لا توجد مرتجعات مبيعات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($salesReturns->hasPages())<div class="card-body d-flex justify-content-center">{{ $salesReturns->links('pagination::bootstrap-5') }}</div>@endif
    </div>
@endsection
