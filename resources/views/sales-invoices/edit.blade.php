@extends('layouts.dashboard')

@section('title', 'Edit ' . $salesInvoice->invoice_number)

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">Sales Invoices</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sales-invoices.show', $salesInvoice) }}" class="text-decoration-none">{{ $salesInvoice->invoice_number }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Edit Sales Invoice</h1>
        <p class="text-muted mb-0">{{ $salesInvoice->invoice_number }}</p>
    </div>

    @include('sales-invoices.partials.form', [
        'salesInvoice' => $salesInvoice,
        'customers' => $customers,
        'products' => $products,
        'existingProducts' => $existingProducts,
        'formAction' => route('sales-invoices.update', $salesInvoice),
        'formMethod' => 'PUT',
        'submitLabel' => 'Update Invoice',
    ])
@endsection
