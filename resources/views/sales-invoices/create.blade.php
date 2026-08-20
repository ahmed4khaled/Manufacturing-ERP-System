@extends('layouts.dashboard')

@section('title', 'إنشاء فاتورة مبيعات - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">فواتير المبيعات</a></li>
            <li class="breadcrumb-item active">إنشاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء فاتورة مبيعات</h1>
        <p class="text-muted mb-0">أضف الموديلات والكميات، وسيعالج النظام المتغيرات تلقائيًا</p>
    </div>

    @include('sales-invoices.partials.form', [
        'customers' => $customers,
        'products' => $products,
        'formAction' => route('sales-invoices.store'),
        'submitLabel' => 'حفظ الفاتورة',
    ])
@endsection
