@extends('layouts.dashboard')

@section('title', 'مرتجع مبيعات جديد - ' . config('app.name'))

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">مرتجع مبيعات جديد</h1>
        <p class="text-muted mb-0">اختر الفاتورة الأصلية ثم حدد الكميات المرتجعة.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('sales-returns.create') }}" class="row g-3 align-items-end">
                <div class="col-lg-9">
                    <label class="form-label">فاتورة المبيعات الأصلية</label>
                    <select name="sales_invoice_id" class="form-select" required>
                        <option value="">اختر الفاتورة</option>
                        @foreach ($invoices as $availableInvoice)
                            <option value="{{ $availableInvoice->id }}" @selected($invoice?->id === $availableInvoice->id)>
                                {{ $availableInvoice->invoice_number }} — {{ $availableInvoice->customer->name }} — {{ $availableInvoice->invoice_date->format('Y-m-d') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3"><button class="btn btn-outline-primary w-100">عرض بنود الفاتورة</button></div>
            </form>
        </div>
    </div>

    @if ($invoice)
        @include('sales-returns.partials.form', [
            'invoice' => $invoice,
            'eligibleItems' => $eligibleItems,
            'formAction' => route('sales-returns.store'),
            'submitLabel' => 'حفظ مسودة المرتجع',
        ])
    @endif
@endsection
