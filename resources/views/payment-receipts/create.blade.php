@extends('layouts.dashboard')

@section('title', 'إنشاء سند قبض - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payment-receipts.index') }}" class="text-decoration-none">سندات القبض</a></li>
            <li class="breadcrumb-item active">إنشاء</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء سند قبض</h1>
        <p class="text-muted mb-0">سجّل دفعة مستلمة من العميل، وسيُنشأ رقم السند تلقائيًا</p>
    </div>

    @include('payment-receipts.partials.form', [
        'customers' => $customers,
        'formAction' => route('payment-receipts.store'),
        'submitLabel' => 'حفظ السند',
    ])
@endsection
