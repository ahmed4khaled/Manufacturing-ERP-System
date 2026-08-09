@extends('layouts.dashboard')

@section('title', 'Create Payment Receipt - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payment-receipts.index') }}" class="text-decoration-none">Payment Receipts</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Create Payment Receipt</h1>
        <p class="text-muted mb-0">Record a payment received from a customer — receipt number is generated automatically</p>
    </div>

    @include('payment-receipts.partials.form', [
        'customers' => $customers,
        'formAction' => route('payment-receipts.store'),
        'submitLabel' => 'Save Receipt',
    ])
@endsection
