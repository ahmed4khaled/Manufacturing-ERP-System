@extends('layouts.dashboard')

@section('title', $supplierPayment->payment_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('dashboard') }}">لوحة التحكم</a></li><li class="breadcrumb-item"><a href="{{ route('supplier-payments.index') }}">مدفوعات الموردين</a></li><li class="breadcrumb-item active">{{ $supplierPayment->payment_number }}</li></ol></nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><div class="d-flex align-items-center gap-2 mb-1"><h1 class="h3 mb-0">{{ $supplierPayment->payment_number }}</h1><span class="badge bg-{{ $supplierPayment->status->badgeClass() }}">{{ $supplierPayment->status->label() }}</span></div><p class="text-muted mb-0">{{ $supplierPayment->supplier?->name }} · {{ $supplierPayment->payment_date?->format('Y-m-d') }}</p></div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('supplier-payments.print-payment', $supplierPayment) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer me-1"></i> طباعة</a>
            @if($supplierPayment->status === \App\Enums\SupplierPaymentStatus::DRAFT)
                <a href="{{ route('supplier-payments.edit', $supplierPayment) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> تعديل</a>
                <form action="{{ route('supplier-payments.confirm', $supplierPayment) }}" method="POST" onsubmit="return confirm('هل تريد تأكيد الدفعة؟ سيتم ترحيلها إلى حساب المورد والمحاسبة.')">@csrf<button class="btn btn-success"><i class="bi bi-check-circle me-1"></i> تأكيد</button></form>
                <form action="{{ route('supplier-payments.destroy', $supplierPayment) }}" method="POST" onsubmit="return confirm('هل تريد حذف مسودة الدفعة؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> حذف</button></form>
            @elseif($supplierPayment->status === \App\Enums\SupplierPaymentStatus::CONFIRMED)
                <form action="{{ route('supplier-payments.cancel', $supplierPayment) }}" method="POST" onsubmit="return confirm('هل تريد إلغاء الدفعة وعكس أثرها المحاسبي؟')">@csrf<button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i> إلغاء الدفعة</button></form>
            @else
                <form action="{{ route('supplier-payments.destroy', $supplierPayment) }}" method="POST" onsubmit="return confirm('هل تريد إخفاء سجل الدفعة الملغاة؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> حذف</button></form>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">المورد</h5></div><div class="card-body"><p class="fw-semibold mb-1"><a href="{{ route('suppliers.show', $supplierPayment->supplier) }}" class="text-decoration-none">{{ $supplierPayment->supplier?->name }}</a></p><p class="text-muted small mb-1">{{ $supplierPayment->supplier?->code }}</p><p class="text-muted small mb-0">{{ $supplierPayment->supplier?->phone }}</p></div></div>
            <div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">بيانات السداد</h5></div><div class="card-body">
                <div class="mb-3"><label class="form-label text-muted small mb-1">المبلغ</label><p class="mb-0 fw-semibold fs-4 text-success">{{ number_format((float) $supplierPayment->amount, 2) }} ج.م</p></div>
                <div class="mb-3"><label class="form-label text-muted small mb-1">طريقة الدفع</label><p class="mb-0">{{ \App\Support\ArabicLabels::paymentMethod($supplierPayment->payment_method) }}</p></div>
                <div class="mb-3"><label class="form-label text-muted small mb-1">المرجع</label><p class="mb-0">{{ $supplierPayment->reference ?: '—' }}</p></div>
                <div><label class="form-label text-muted small mb-1">أنشأها</label><p class="mb-0">{{ $supplierPayment->creator?->name ?: '—' }}</p></div>
            </div></div>
            @if($supplierPayment->notes)<div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">ملاحظات</h5></div><div class="card-body"><p class="mb-0">{{ $supplierPayment->notes }}</p></div></div>@endif
        </div>

        <div class="col-lg-8">
            <div class="card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">توزيع الدفعة</h5><span class="badge bg-{{ $supplierPayment->payment_type === 'advance' ? 'warning text-dark' : 'primary' }}">{{ $supplierPayment->payment_type === 'advance' ? 'دفعة مقدمة غير موزعة' : 'سداد فواتير' }}</span></div>
                @if($allocationBreakdown->isNotEmpty())
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>الفاتورة</th><th class="text-end">إجمالي الفاتورة</th><th class="text-end">مدفوع سابقًا</th><th class="text-end">هذه الدفعة</th><th class="text-end">المتبقي بعدها</th></tr></thead><tbody>
                        @foreach($allocationBreakdown as $row)<tr><td><a href="{{ route('supplier-invoices.show', $row['invoice']) }}" class="fw-semibold text-decoration-none">{{ $row['invoice']->invoice_number }}</a><div class="small text-muted">{{ $row['invoice']->invoice_date?->format('Y-m-d') }}</div></td><td class="text-end">{{ number_format((float) $row['invoice_total'], 2) }}</td><td class="text-end">{{ number_format((float) $row['previous_paid'], 2) }}</td><td class="text-end fw-semibold text-success">{{ number_format((float) $row['allocation_amount'], 2) }}</td><td class="text-end fw-semibold">{{ number_format((float) $row['remaining'], 2) }}</td></tr>@endforeach
                    </tbody></table></div>
                @else
                    <div class="card-body"><div class="alert alert-light border mb-0">هذه دفعة مقدمة غير موزعة على فاتورة. @if($supplierPayment->purchaseOrder)مرتبطة بأمر الشراء <a href="{{ route('purchase-orders.show', $supplierPayment->purchaseOrder) }}">{{ $supplierPayment->purchaseOrder->purchase_order_number }}</a>.@endif</div></div>
                @endif
            </div>
            <div class="card mt-4"><div class="card-header bg-white"><h5 class="card-title mb-0">أثر الدفعة</h5></div><div class="card-body"><div class="alert alert-{{ $supplierPayment->status === \App\Enums\SupplierPaymentStatus::CONFIRMED ? 'success' : ($supplierPayment->status === \App\Enums\SupplierPaymentStatus::DRAFT ? 'secondary' : 'danger') }} mb-0">
                @if($supplierPayment->status === \App\Enums\SupplierPaymentStatus::CONFIRMED)تم خصم قيمة الدفعة من رصيد المورد وترحيل القيد المحاسبي.@elseif($supplierPayment->status === \App\Enums\SupplierPaymentStatus::DRAFT)المسودة لا تؤثر على رصيد المورد أو القيود المحاسبية حتى التأكيد.@elseتم إلغاء الدفعة وعكس أثرها على الفواتير والمحاسبة.@endif
            </div></div></div>
        </div>
    </div>
@endsection
