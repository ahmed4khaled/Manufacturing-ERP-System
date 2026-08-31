@extends('layouts.dashboard')

@section('title', 'مدفوعات الموردين - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('dashboard') }}">لوحة التحكم</a></li><li class="breadcrumb-item active">مدفوعات الموردين</li></ol></nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">مدفوعات الموردين</h1><p class="text-muted mb-0">مسودات المدفوعات والمدفوعات المؤكدة والملغاة</p></div>
        <a href="{{ route('supplier-payments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> إنشاء دفعة</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" action="{{ route('supplier-payments.index') }}" class="row g-3 mb-4">
            <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="رقم الدفعة أو المورد..." value="{{ request('search') }}"></div></div>
            <div class="col-md-2"><select name="supplier_id" class="form-select"><option value="">كل الموردين</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" title="من تاريخ"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" title="إلى تاريخ"></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach (\App\Enums\SupplierPaymentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">تصفية</button> @if(request()->hasAny(['search','supplier_id','date_from','date_to','status']))<a href="{{ route('supplier-payments.index') }}" class="btn btn-outline-secondary">مسح</a>@endif</div>
        </form>

        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>رقم الدفعة</th><th>المورد</th><th>التاريخ</th><th class="text-end">المبلغ</th><th>طريقة الدفع</th><th>التوزيع</th><th>الحالة</th><th class="text-end">الإجراءات</th></tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td><a href="{{ route('supplier-payments.show-payment', $payment) }}" class="text-decoration-none fw-semibold">{{ $payment->payment_number }}</a></td>
                        <td>{{ $payment->supplier?->name }}</td>
                        <td>{{ $payment->payment_date?->format('Y-m-d') }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $payment->amount, 2) }} ج.م</td>
                        <td>{{ \App\Support\ArabicLabels::paymentMethod($payment->payment_method) }}</td>
                        <td>@if($payment->payment_type === 'advance')<span class="badge bg-warning text-dark">دفعة مقدمة</span>@elseif($payment->allocations->count() > 1)<span class="badge bg-primary">{{ $payment->allocations->count() }} فواتير</span>@elseif($payment->invoice)<a href="{{ route('supplier-invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>@else — @endif</td>
                        <td><span class="badge bg-{{ $payment->status->badgeClass() }}">{{ $payment->status->label() }}</span></td>
                        <td class="text-end"><div class="btn-group btn-group-sm">
                            <a href="{{ route('supplier-payments.print-payment', $payment) }}" target="_blank" class="btn btn-outline-dark" title="طباعة"><i class="bi bi-printer"></i></a>
                            <a href="{{ route('supplier-payments.show-payment', $payment) }}" class="btn btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                            @if($payment->status === \App\Enums\SupplierPaymentStatus::DRAFT)
                                <a href="{{ route('supplier-payments.edit', $payment) }}" class="btn btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('supplier-payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف مسودة الدفعة؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button></form>
                            @elseif($payment->status === \App\Enums\SupplierPaymentStatus::CANCELLED)
                                <form action="{{ route('supplier-payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد إخفاء سجل الدفعة الملغاة؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-cash-stack display-6 d-block mb-2"></i>لا توجد مدفوعات موردين.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($payments->hasPages())<div class="d-flex justify-content-center mt-4">{{ $payments->links('pagination::bootstrap-5') }}</div>@endif
    </div></div>
@endsection
