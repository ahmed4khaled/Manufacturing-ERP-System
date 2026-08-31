@extends('layouts.dashboard')

@section('title', 'كشف أجور ' . $worker->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">كشف أجور: {{ $worker->name }}</h1>
            <div class="text-muted">{{ $worker->code }} · {{ $worker->specialization ?: 'بدون تخصص' }}</div>
        </div>
        <a href="{{ route('worker-wages.index') }}" class="btn btn-outline-secondary">سجل الأجور</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['إجمالي المستحق', $grossTotal, 'primary'], ['المدفوع', $paidTotal, 'success'], ['المتبقي', $outstanding, 'danger']] as [$label, $value, $color])
            <div class="col-md-4"><div class="card border-{{ $color }}"><div class="card-body"><div class="text-muted">{{ $label }}</div><strong class="fs-4">{{ number_format((float) $value, 2) }}</strong></div></div></div>
        @endforeach
    </div>

    @can('wages.pay')
        @if ((float) $outstanding > 0)
            <div class="card mb-4" id="pay-worker-wage">
                <div class="card-header"><strong>تسجيل دفعة</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('worker-wages.payments.store') }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="worker_id" value="{{ $worker->id }}">
                        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                        <div class="col-md-3"><label class="form-label">التاريخ</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">المبلغ</label><input type="number" name="amount" value="{{ old('amount') }}" min="0.01" max="{{ $outstanding }}" step="0.01" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">الدفع من</label><select name="payment_account_id" class="form-select" required><option value="">اختر خزينة/بنك</option>@foreach ($paymentAccounts as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">المرجع</label><input name="reference" value="{{ old('reference') }}" class="form-control" maxlength="255"></div>
                        <div class="col-md-9"><label class="form-label">البيان</label><input name="description" value="{{ old('description', 'سداد أجور إنتاج - ' . $worker->name) }}" class="form-control" maxlength="2000" required></div>
                        <div class="col-md-3 d-grid align-items-end"><button class="btn btn-primary mt-auto">تسجيل وترحيل الدفعة</button></div>
                        <div class="col-12"><small class="text-muted">ستُوزّع الدفعة تلقائيًا على أقدم الاستحقاقات أولًا (FIFO).</small></div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    <div class="card mb-4">
        <div class="card-header"><strong>الاستحقاقات</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>التاريخ</th><th>أمر الإنتاج</th><th>العملية</th><th class="text-end">المستحق</th><th class="text-end">المدفوع</th><th class="text-end">المتبقي</th><th>الحالة</th></tr></thead>
            <tbody>@forelse ($wages as $wage)<tr>
                <td>{{ $wage->earned_at->format('Y-m-d') }}</td><td><a href="{{ route('production-orders.show', $wage->productionOrder) }}">{{ $wage->productionOrder->order_number }}</a></td><td>{{ $wage->operation->operation_name }}</td>
                <td class="text-end">{{ number_format((float) $wage->gross_amount, 2) }}</td><td class="text-end">{{ number_format((float) $wage->paid_amount, 2) }}</td><td class="text-end">{{ number_format((float) $wage->remaining_amount, 2) }}</td><td>{{ $wage->status->label() }}</td>
            </tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">لا توجد استحقاقات.</td></tr>@endforelse</tbody>
        </table></div>
        @if ($wages->hasPages())<div class="card-footer">{{ $wages->appends(request()->except('wages_page'))->links('pagination::bootstrap-5') }}</div>@endif
    </div>

    <div class="card">
        <div class="card-header"><strong>سندات الدفع</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>الرقم</th><th>التاريخ</th><th>حساب الدفع</th><th class="text-end">المبلغ</th><th>الحالة</th><th>القيد</th></tr></thead>
            <tbody>@forelse ($payments as $payment)<tr>
                <td><a href="{{ route('worker-wages.payments.show', $payment) }}">{{ $payment->payment_number }}</a></td><td>{{ $payment->payment_date->format('Y-m-d') }}</td><td>{{ $payment->paymentAccount->name }}</td><td class="text-end">{{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->status->label() }}</td><td>{{ $payment->journalEntry?->entry_number ?? '—' }}</td>
            </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">لا توجد دفعات.</td></tr>@endforelse</tbody>
        </table></div>
        @if ($payments->hasPages())<div class="card-footer">{{ $payments->appends(request()->except('payments_page'))->links('pagination::bootstrap-5') }}</div>@endif
    </div>
@endsection
