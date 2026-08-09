@extends('layouts.dashboard')

@section('title', 'Payment Receipts - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active">Payment Receipts</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Payment Receipts</h1>
            <p class="text-muted mb-0">Record money received from customers</p>
        </div>
        <a href="{{ route('payment-receipts.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Create Receipt
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('payment-receipts.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Receipt #, customer..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" placeholder="From" value="{{ request('date_from') }}" title="Date from">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" placeholder="To" value="{{ request('date_to') }}" title="Date to">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\Enums\PaymentReceiptStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if (request()->hasAny(['search', 'status', 'customer_id', 'date_from', 'date_to']))
                        <a href="{{ route('payment-receipts.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr>
                                <td>
                                    <a href="{{ route('payment-receipts.show', $receipt) }}" class="text-decoration-none fw-semibold">
                                        {{ $receipt->receipt_number }}
                                    </a>
                                </td>
                                <td>{{ $receipt->customer?->name }}</td>
                                <td>{{ $receipt->payment_date->format('M d, Y') }}</td>
                                <td class="text-end">{{ number_format($receipt->amount, 2) }} EGP</td>
                                <td>{{ $receipt->payment_method->label() }}</td>
                                <td><span class="badge bg-{{ $receipt->status->badgeClass() }}">{{ $receipt->status->label() }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('payment-receipts.show', $receipt) }}" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        @if ($receipt->status === \App\Enums\PaymentReceiptStatus::DRAFT)
                                            <a href="{{ route('payment-receipts.edit', $receipt) }}" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <form action="{{ route('payment-receipts.destroy', $receipt) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this draft receipt?')">
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
                                    <i class="bi bi-cash-coin display-6 d-block mb-2"></i>
                                    No payment receipts found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($receipts->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $receipts->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
