@extends('layouts.dashboard')

@section('title', $customer->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customers.index') }}" class="text-decoration-none">Customers</a></li>
            <li class="breadcrumb-item active">{{ $customer->code }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $customer->name }}</h1>
                <span class="badge bg-{{ $customer->status->badgeClass() }}">{{ $customer->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $customer->code }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this customer?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Phone</label>
                            <p class="mb-0 fw-semibold">{{ $customer->phone }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Phone 2</label>
                            <p class="mb-0 fw-semibold">{{ $customer->phone_2 ?? '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">City</label>
                            <p class="mb-0 fw-semibold">{{ $customer->city ?? '—' }}</p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Address</label>
                            <p class="mb-0 fw-semibold">{{ $customer->address ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($customer->notes)
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ $customer->notes }}</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Account Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Customer Code</label>
                        <p class="mb-0 fw-semibold">{{ $customer->code }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Credit Limit</label>
                        <p class="mb-0 fw-semibold fs-5 text-primary">{{ number_format($customer->credit_limit, 2) }} EGP</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-{{ $customer->status->badgeClass() }}">{{ $customer->status->label() }}</span>
                        </p>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">Created</label>
                        <p class="mb-0 small">{{ $customer->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="mt-2">
                        <label class="form-label text-muted small mb-1">Last Updated</label>
                        <p class="mb-0 small">{{ $customer->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Ledger Summary</h5>
                    <a href="{{ route('customer-ledger.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Current Balance</label>
                        <p class="mb-0 fw-semibold fs-4 {{ (float) $ledgerSummary['balance'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($ledgerSummary['balance'], 2) }} EGP
                        </p>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Total Debit</span>
                        <strong>{{ number_format($ledgerSummary['total_debit'], 2) }} EGP</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Total Credit</span>
                        <strong>{{ number_format($ledgerSummary['total_credit'], 2) }} EGP</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($recentLedgerEntries->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Recent Ledger Entries</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLedgerEntries as $entry)
                                <tr>
                                    <td>{{ $entry->transaction_date->format('M d, Y') }}</td>
                                    <td>
                                        <code>{{ $entry->reference_number ?? '—' }}</code>
                                        <br><small class="text-muted">{{ $entry->reference_type->label() }}</small>
                                    </td>
                                    <td>{{ $entry->description }}</td>
                                    <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                                    <td class="text-end">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($recentPaymentReceipts->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Payment Receipts</h5>
                <a href="{{ route('payment-receipts.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentPaymentReceipts as $receipt)
                                <tr>
                                    <td>
                                        <a href="{{ route('payment-receipts.show', $receipt) }}" class="text-decoration-none fw-semibold">
                                            {{ $receipt->receipt_number }}
                                        </a>
                                    </td>
                                    <td>{{ $receipt->payment_date->format('M d, Y') }}</td>
                                    <td class="text-end">{{ number_format($receipt->amount, 2) }} EGP</td>
                                    <td>{{ $receipt->payment_method->label() }}</td>
                                    <td><span class="badge bg-{{ $receipt->status->badgeClass() }}">{{ $receipt->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-3">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Customers
        </a>
    </div>
@endsection
