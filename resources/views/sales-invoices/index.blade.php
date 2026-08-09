@extends('layouts.dashboard')

@section('title', 'Sales Invoices - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active">Sales Invoices</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Sales Invoices</h1>
            <p class="text-muted mb-0">Manage customer sales invoices</p>
        </div>
        <a href="{{ route('sales-invoices.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Create Invoice
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('sales-invoices.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Invoice #, customer..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\Enums\SalesInvoiceStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if (request()->hasAny(['search', 'status', 'customer_id']))
                        <a href="{{ route('sales-invoices.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-decoration-none fw-semibold">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ $invoice->customer?->name }}</td>
                                <td>{{ $invoice->invoice_date->format('M d, Y') }}</td>
                                <td><span class="badge bg-{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                                <td class="text-end">{{ number_format($invoice->total_quantity) }}</td>
                                <td class="text-end">{{ number_format($invoice->total_amount, 2) }} EGP</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('sales-invoices.show', $invoice) }}" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        @if ($invoice->status === \App\Enums\SalesInvoiceStatus::DRAFT)
                                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <form action="{{ route('sales-invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this draft invoice?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-receipt display-6 d-block mb-2"></i>
                                    No sales invoices found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
