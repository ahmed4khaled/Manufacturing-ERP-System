@extends('layouts.dashboard')

@section('title', 'أجور العاملين')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">أجور العاملين بالإنتاج</h1>
            <p class="text-muted mb-0">استحقاقات مولّدة من أوامر الإنتاج المكتملة؛ الدفع تسوية للالتزام وليس مصروفًا سريعًا.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach (['gross' => ['إجمالي المستحق', 'primary'], 'paid' => ['المدفوع', 'success'], 'remaining' => ['المتبقي', 'danger']] as $key => [$label, $color])
            <div class="col-md-4">
                <div class="card border-{{ $color }} h-100"><div class="card-body">
                    <div class="text-muted">{{ $label }}</div>
                    <div class="h4 mb-0">{{ number_format((float) $totals[$key], 2) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">العامل</label>
                    <select name="worker_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($workers as $worker)
                            <option value="{{ $worker->id }}" @selected((string) request('worker_id') === (string) $worker->id)>{{ $worker->code }} - {{ $worker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">أمر الإنتاج</label>
                    <select name="production_order_id" class="form-select"><option value="">الكل</option>@foreach ($productionOrders as $order)<option value="{{ $order->id }}" @selected((string) request('production_order_id') === (string) $order->id)>{{ $order->order_number }}</option>@endforeach</select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">العملية</label>
                    <select name="operation_id" class="form-select"><option value="">الكل</option>@foreach ($operations as $operation)<option value="{{ $operation->id }}" @selected((string) request('operation_id') === (string) $operation->id)>{{ $operation->operation_name }}</option>@endforeach</select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        @foreach (\App\Enums\ProductionWorkerWageStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-md-1 d-grid"><button class="btn btn-outline-primary">تطبيق</button></div>
                <div class="col-12"><a href="{{ route('worker-wages.index') }}" class="btn btn-link p-0">مسح الفلاتر</a></div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>أرصدة العاملين</strong></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>العامل</th><th class="text-end">المستحق</th><th class="text-end">المدفوع</th><th class="text-end">المتبقي</th><th>الحالة</th><th>آخر دفعة</th><th>الإجراءات</th></tr></thead>
            <tbody>@forelse ($balances as $balance)<tr>
                <td>{{ $balance->worker->code }} - {{ $balance->worker->name }}</td>
                <td class="text-end">{{ number_format((float) $balance->gross_total, 2) }}</td><td class="text-end">{{ number_format((float) $balance->paid_total, 2) }}</td><td class="text-end fw-semibold">{{ number_format((float) $balance->remaining_total, 2) }}</td>
                <td>@if ((float) $balance->remaining_total === 0.0)<span class="badge bg-success">مدفوع</span>@elseif ((float) $balance->paid_total > 0)<span class="badge bg-warning text-dark">مدفوع جزئيًا</span>@else<span class="badge bg-danger">غير مدفوع</span>@endif</td>
                <td>{{ $balance->worker->latestWagePayment?->payment_date?->format('Y-m-d') ?? '—' }}</td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('worker-wages.show', $balance->worker) }}">عرض</a> @can('wages.pay') @if ((float) $balance->remaining_total > 0)<a class="btn btn-sm btn-primary" href="{{ route('worker-wages.show', $balance->worker) }}#pay-worker-wage">صرف أجر</a>@endif @endcan</td>
            </tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">لا توجد أرصدة مطابقة.</td></tr>@endforelse</tbody>
        </table></div>
        @if ($balances->hasPages())<div class="card-footer">{{ $balances->links('pagination::bootstrap-5') }}</div>@endif
    </div>

    <div class="card">
        <div class="card-header"><strong>تفاصيل استحقاقات الإنتاج</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>التاريخ</th><th>العامل</th><th>أمر الإنتاج</th><th>العملية</th>
                    <th class="text-end">الكمية</th><th class="text-end">سعر الوحدة</th><th class="text-end">المستحق</th>
                    <th class="text-end">المدفوع</th><th class="text-end">المتبقي</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    @forelse ($wages as $wage)
                        <tr>
                            <td>{{ $wage->earned_at->format('Y-m-d') }}</td>
                            <td><a href="{{ route('worker-wages.show', $wage->worker) }}">{{ $wage->worker->name }}</a></td>
                            <td><a href="{{ route('production-orders.show', $wage->productionOrder) }}">{{ $wage->productionOrder->order_number }}</a></td>
                            <td>{{ $wage->operation->operation_name }}</td>
                            <td class="text-end">{{ number_format((float) $wage->quantity, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $wage->unit_rate, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $wage->gross_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $wage->paid_amount, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $wage->remaining_amount, 2) }}</td>
                            <td><span class="badge {{ $wage->status === \App\Enums\ProductionWorkerWageStatus::PAID ? 'bg-success' : ($wage->status === \App\Enums\ProductionWorkerWageStatus::PARTIALLY_PAID ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $wage->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-5">لا توجد استحقاقات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($wages->hasPages())<div class="card-footer">{{ $wages->links('pagination::bootstrap-5') }}</div>@endif
    </div>
@endsection
