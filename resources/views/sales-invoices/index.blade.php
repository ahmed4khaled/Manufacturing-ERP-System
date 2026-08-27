@extends('layouts.dashboard')

@section('title', 'فواتير المبيعات - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item active">فواتير المبيعات</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">فواتير المبيعات</h1>
            <p class="text-muted mb-0">إدارة فواتير مبيعات العملاء</p>
        </div>
        <a href="{{ route('sales-invoices.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إنشاء فاتورة
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('sales-invoices.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="رقم الفاتورة أو العميل..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select">
                        <option value="">كل العملاء</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">كل الحالات</option>
                        @foreach (\App\Enums\SalesInvoiceStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    @if (request()->hasAny(['search', 'status', 'customer_id']))
                        <a href="{{ route('sales-invoices.index') }}" class="btn btn-outline-secondary">مسح الفلاتر</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th class="text-end">الكمية</th>
                            <th class="text-end">الإجمالي</th>
                            <th>السداد</th>

                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-decoration-none fw-semibold">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ $invoice->customer?->name }}</td>
                                <td>{{ $invoice->invoice_date->format('Y-m-d') }}</td>
                                <td><span class="badge bg-{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                                <td class="text-end">{{ number_format($invoice->total_quantity, 3) }}</td>
                                <td class="text-end">{{ number_format($invoice->total_amount, 2) }} ج.م</td>
                             

<td>
    <span class="badge bg-{{ $invoice->payment_status_badge_class }}">
        {{ $invoice->payment_status_label }}
    </span>

    @if ($invoice->payment_status === 'partially_paid')
        <div class="small text-muted mt-1">
            المتبقي:
            {{ number_format($invoice->remaining_amount, 2) }}
            ج.م
        </div>
    @endif
</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                         <a
                                            href="{{ route(
                                                'sales-invoices.print',
                                                $invoice
                                            ) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-dark"
                                            title="طباعة الفاتورة"
                                        >
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <a href="{{ route('sales-invoices.show', $invoice) }}" class="btn btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                                        @if ($invoice->status === \App\Enums\SalesInvoiceStatus::DRAFT)
                                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="btn btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                            <form action="{{ route('sales-invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف مسودة الفاتورة؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @elseif ($invoice->status === \App\Enums\SalesInvoiceStatus::CANCELLED)
                                            <form action="{{ route('sales-invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟ سيتم إخفاؤه من السجلات النشطة مع الاحتفاظ بتاريخه.')">
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
                                    <i class="bi bi-receipt display-6 d-block mb-2"></i>
                                    لا توجد فواتير مبيعات.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
