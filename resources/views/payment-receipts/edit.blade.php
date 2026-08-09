@extends('layouts.dashboard')

@section('title', 'Edit ' . $paymentReceipt->receipt_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payment-receipts.index') }}" class="text-decoration-none">Payment Receipts</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payment-receipts.show', $paymentReceipt) }}" class="text-decoration-none">{{ $paymentReceipt->receipt_number }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Edit Payment Receipt</h1>
        <p class="text-muted mb-0">{{ $paymentReceipt->receipt_number }}</p>
    </div>

    @include('payment-receipts.partials.form', [
        'paymentReceipt' => $paymentReceipt,
        'customers' => $customers,
        'formAction' => route('payment-receipts.update', $paymentReceipt),
        'formMethod' => 'PUT',
        'submitLabel' => 'Update Receipt',
    ])
@endsection
