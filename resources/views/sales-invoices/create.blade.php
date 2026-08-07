@extends('layouts.dashboard')

@section('title', 'Create Sales Invoice - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">Sales Invoices</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Create Sales Invoice</h1>
        <p class="text-muted mb-0">Add models and quantities — variants are handled automatically</p>
    </div>

    @include('sales-invoices.partials.form', [
        'customers' => $customers,
        'products' => $products,
        'formAction' => route('sales-invoices.store'),
        'submitLabel' => 'Save Invoice',
    ])
@endsection
