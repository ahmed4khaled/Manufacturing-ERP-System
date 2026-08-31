@extends('layouts.dashboard')

@section('title', 'إنشاء دفعة مورد - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="{{ route('supplier-payments.index') }}">مدفوعات الموردين</a></li>
        <li class="breadcrumb-item active">إنشاء</li>
    </ol></nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء دفعة مورد</h1>
        <p class="text-muted mb-0">سجّل الدفعة كمسودة، ووزّعها على فاتورة أو أكثر، ثم أكّدها للترحيل.</p>
    </div>

    @include('procurement.payments.partials.form', [
        'suppliers' => $suppliers,
        'orders' => $orders,
        'formAction' => route('supplier-payments.store'),
        'submitLabel' => 'حفظ المسودة',
    ])
@endsection
