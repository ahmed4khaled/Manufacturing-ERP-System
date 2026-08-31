@extends('layouts.dashboard')

@section('title', $payment->payment_number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">سند دفع أجور {{ $payment->payment_number }}</h1><span class="badge {{ $payment->status === \App\Enums\WorkerWagePaymentStatus::POSTED ? 'bg-success' : 'bg-secondary' }}">{{ $payment->status->label() }}</span></div>
        <a href="{{ route('worker-wages.show', $payment->worker) }}" class="btn btn-outline-secondary">كشف {{ $payment->worker->name }}</a>
    </div>

    <div class="card mb-4"><div class="card-body"><div class="row g-4">
        @foreach (['العامل' => $payment->worker->name, 'التاريخ' => $payment->payment_date->format('Y-m-d'), 'المبلغ' => number_format((float) $payment->amount, 2), 'حساب الدفع' => $payment->paymentAccount->name, 'المرجع' => $payment->reference ?: '—', 'أدخلها' => $payment->creator?->name ?? '—'] as $label => $value)
            <div class="col-md-4"><div class="small text-muted">{{ $label }}</div><strong>{{ $value }}</strong></div>
        @endforeach
        <div class="col-12"><div class="small text-muted">البيان</div><strong>{{ $payment->description }}</strong></div>
    </div></div></div>

    <div class="card mb-4">
        <div class="card-header"><strong>توزيع الدفعة على الاستحقاقات (FIFO)</strong></div>
        <div class="table-responsive"><table class="table mb-0"><thead><tr><th>تاريخ الاستحقاق</th><th>أمر الإنتاج</th><th>العملية</th><th class="text-end">المخصص</th><th>الحالة</th></tr></thead><tbody>
            @foreach ($payment->allocations as $allocation)<tr><td>{{ $allocation->wage->earned_at->format('Y-m-d') }}</td><td>{{ $allocation->wage->productionOrder->order_number }}</td><td>{{ $allocation->wage->operation->operation_name }}</td><td class="text-end">{{ number_format((float) $allocation->amount, 2) }}</td><td>{{ $allocation->reversed_at ? 'معكوس' : 'نشط' }}</td></tr>@endforeach
        </tbody></table></div>
    </div>

    @foreach (['journalEntry' => 'القيد المحاسبي', 'reversalJournalEntry' => 'قيد العكس'] as $relation => $title)
        @if ($payment->{$relation})
            <div class="card mb-4"><div class="card-header d-flex justify-content-between"><strong>{{ $title }}</strong><a href="{{ route('accounting.journals', ['entry_id' => $payment->{$relation}->id]) }}">{{ $payment->{$relation}->entry_number }}</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الحساب</th><th class="text-end">مدين</th><th class="text-end">دائن</th></tr></thead><tbody>
                @foreach ($payment->{$relation}->lines as $line)<tr><td>{{ $line->account->code }} - {{ $line->account->name }}</td><td class="text-end">{{ number_format((float) $line->debit, 2) }}</td><td class="text-end">{{ number_format((float) $line->credit, 2) }}</td></tr>@endforeach
            </tbody></table></div></div>
        @endif
    @endforeach

    @can('wages.reverse')
        @if ($payment->status === \App\Enums\WorkerWagePaymentStatus::POSTED)
            <div class="card border-danger"><div class="card-body"><h2 class="h5">عكس الدفعة</h2><p class="text-muted">ينشئ النظام قيدًا معاكسًا ويعيد فتح الاستحقاقات؛ لا يحذف السند.</p><form method="POST" action="{{ route('worker-wages.payments.reverse', $payment) }}" class="d-flex gap-2">@csrf<input type="date" name="reversal_date" value="{{ now()->toDateString() }}" class="form-control w-auto" required><button class="btn btn-outline-danger">إنشاء قيد عكس</button></form></div></div>
        @endif
    @endcan
@endsection
