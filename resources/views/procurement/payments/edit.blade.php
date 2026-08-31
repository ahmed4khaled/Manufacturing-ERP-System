@extends('layouts.dashboard')

@section('title', 'تعديل ' . $supplierPayment->payment_number)

@section('breadcrumb')
    <nav aria-label="مسار التنقل"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="{{ route('supplier-payments.index') }}">مدفوعات الموردين</a></li>
        <li class="breadcrumb-item"><a href="{{ route('supplier-payments.show-payment', $supplierPayment) }}">{{ $supplierPayment->payment_number }}</a></li>
        <li class="breadcrumb-item active">تعديل</li>
    </ol></nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">تعديل {{ $supplierPayment->payment_number }}</h1>
        <p class="text-muted mb-0">يمكن تعديل بيانات الدفعة وتوزيعها ما دامت مسودة.</p>
    </div>

    @include('procurement.payments.partials.form', [
        'supplierPayment' => $supplierPayment,
        'suppliers' => $suppliers,
        'orders' => $orders,
        'formAction' => route('supplier-payments.update', $supplierPayment),
        'formMethod' => 'PUT',
        'submitLabel' => 'حفظ التعديلات',
    ])
@endsection
