@extends('layouts.dashboard')

@section('title', 'تعديل ' . $salesReturn->return_number . ' - ' . config('app.name'))

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">تعديل مرتجع المبيعات {{ $salesReturn->return_number }}</h1>
        <p class="text-muted mb-0">يمكن تعديل المسودة فقط قبل ترحيل أثرها المالي والمخزني.</p>
    </div>

    @include('sales-returns.partials.form', [
        'salesReturn' => $salesReturn,
        'invoice' => $invoice,
        'eligibleItems' => $eligibleItems,
        'formAction' => route('sales-returns.update', $salesReturn),
        'formMethod' => 'PUT',
        'submitLabel' => 'تحديث المرتجع',
    ])
@endsection
