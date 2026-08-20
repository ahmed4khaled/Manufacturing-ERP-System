@extends('layouts.dashboard')

@section('title', $paymentReceipt->receipt_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payment-receipts.index') }}" class="text-decoration-none">سندات القبض</a></li>
            <li class="breadcrumb-item active">{{ $paymentReceipt->receipt_number }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $paymentReceipt->receipt_number }}</h1>
                <span class="badge bg-{{ $paymentReceipt->status->badgeClass() }}">{{ $paymentReceipt->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $paymentReceipt->customer?->name }} · {{ $paymentReceipt->payment_date->format('Y-m-d') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a
    href="{{ route('payment-receipts.print', $paymentReceipt) }}"
    target="_blank"
    class="btn btn-outline-dark"
>
    <i class="bi bi-printer me-1"></i>
    طباعة
</a>
            @if ($paymentReceipt->status === \App\Enums\PaymentReceiptStatus::DRAFT)
                <a href="{{ route('payment-receipts.edit', $paymentReceipt) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> تعديل</a>
                <form action="{{ route('payment-receipts.confirm', $paymentReceipt) }}" method="POST" onsubmit="return confirm('هل تريد تأكيد السند؟ سيتم ترحيل المبلغ دائنًا إلى حساب العميل.')">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> تأكيد</button>
                </form>
                <form action="{{ route('payment-receipts.destroy', $paymentReceipt) }}" method="POST" onsubmit="return confirm('هل تريد حذف مسودة السند؟')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> حذف</button>
                </form>
            @elseif ($paymentReceipt->status === \App\Enums\PaymentReceiptStatus::CONFIRMED)
                <form action="{{ route('payment-receipts.cancel', $paymentReceipt) }}" method="POST" onsubmit="return confirm('هل تريد إلغاء هذا السند؟')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i> إلغاء السند</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">العميل</h5></div>
                <div class="card-body">
                    <p class="fw-semibold mb-1">
                        <a href="{{ route('customers.show', $paymentReceipt->customer) }}" class="text-decoration-none">
                            {{ $paymentReceipt->customer?->name }}
                        </a>
                    </p>
                    <p class="text-muted small mb-1">{{ $paymentReceipt->customer?->code }}</p>
                    <p class="text-muted small mb-0">{{ $paymentReceipt->customer?->phone }}</p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white"><h5 class="card-title mb-0">السداد</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">المبلغ</label>
                        <p class="mb-0 fw-semibold fs-4 text-success">{{ number_format($paymentReceipt->amount, 2) }} ج.م</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">طريقة الدفع</label>
                        <p class="mb-0 fw-semibold">{{ $paymentReceipt->payment_method->label() }}</p>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">رقم المرجع</label>
                        <p class="mb-0 fw-semibold">{{ $paymentReceipt->reference_number ?? '—' }}</p>
                    </div>
                </div>
            </div>

            @if ($paymentReceipt->notes)
                <div class="card mt-4">
                    <div class="card-header bg-white"><h5 class="card-title mb-0">ملاحظات</h5></div>
                    <div class="card-body"><p class="mb-0 text-muted">{{ $paymentReceipt->notes }}</p></div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">تفاصيل السند</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">رقم السند</label>
                            <p class="mb-0 fw-semibold">{{ $paymentReceipt->receipt_number }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">تاريخ السداد</label>
                            <p class="mb-0 fw-semibold">{{ $paymentReceipt->payment_date->format('Y-m-d') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">الحالة</label>
                            <p class="mb-0">
                                <span class="badge bg-{{ $paymentReceipt->status->badgeClass() }}">{{ $paymentReceipt->status->label() }}</span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">أنشأه</label>
                            <p class="mb-0 fw-semibold">{{ $paymentReceipt->creator?->name ?? '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">أُنشئ في</label>
                            <p class="mb-0 fw-semibold">{{ $paymentReceipt->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($ledgerEntry)
                <div class="card mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">قيد حساب العميل</h5>
                        <a href="{{ route('customer-ledger.index', ['customer_id' => $paymentReceipt->customer_id]) }}" class="btn btn-sm btn-outline-primary">عرض كشف الحساب</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>المرجع</th>
                                        <th>الوصف</th>
                                        <th class="text-end">مدين</th>
                                        <th class="text-end">دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $ledgerEntry->transaction_date->format('Y-m-d') }}</td>
                                        <td>
                                            <code>{{ $ledgerEntry->reference_number }}</code>
                                            <br><small class="text-muted">{{ $ledgerEntry->reference_type->label() }}</small>
                                        </td>
                                        <td>{{ $ledgerEntry->description }}</td>
                                        <td class="text-end">{{ $ledgerEntry->debit > 0 ? number_format($ledgerEntry->debit, 2) : '—' }}</td>
                                        <td class="text-end">{{ $ledgerEntry->credit > 0 ? number_format($ledgerEntry->credit, 2) : '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('payment-receipts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> العودة إلى السندات</a>
    </div>
@endsection
