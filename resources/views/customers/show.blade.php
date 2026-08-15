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
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('sales-invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Sales Invoice
            </a>
            <a href="{{ route('payment-receipts.create') }}" class="btn btn-outline-primary">
                <i class="bi bi-cash-coin me-1"></i> New Payment Receipt
            </a>
            <a href="{{ route('customer-ledger.index', ['customer_id' => $customer->id]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-journal-text me-1"></i> View Statement
            </a>
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Edit Customer
            </a>
            <a
    href="{{ route('customer-ledger.print', $customer) }}"
    target="_blank"
    class="btn btn-outline-dark"
>
    <i class="bi bi-printer me-1"></i>
    طباعة كشف الحساب
</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total Sales</p>
                    <p class="mb-0 fw-semibold fs-4">{{ number_format($ledgerSummary['total_debit'], 2) }} EGP</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total Payments</p>
                    <p class="mb-0 fw-semibold fs-4">{{ number_format($ledgerSummary['total_credit'], 2) }} EGP</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Current Balance</p>
                    <p class="mb-0 fw-semibold fs-4 {{ (float) $ledgerSummary['balance'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($ledgerSummary['balance'], 2) }} EGP</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Credit Limit</p>
                    <p class="mb-0 fw-semibold fs-4 text-primary">{{ number_format($customer->credit_limit, 2) }} EGP</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Available Credit</p>
                    <p class="mb-0 fw-semibold fs-4 {{ (float) $availableCredit < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($availableCredit, 2) }} EGP</p>
                    @if ((float) $availableCredit < 0)
                        <span class="badge bg-danger mt-1">OVER CREDIT LIMIT</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">Code</label><p class="mb-0 fw-semibold">{{ $customer->code }}</p></div>
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">Name</label><p class="mb-0 fw-semibold">{{ $customer->name }}</p></div>
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">Phone</label><p class="mb-0 fw-semibold">{{ $customer->phone }}</p></div>
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">Phone 2</label><p class="mb-0 fw-semibold">{{ $customer->phone_2 ?? '-' }}</p></div>
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">City</label><p class="mb-0 fw-semibold">{{ $customer->city ?? '-' }}</p></div>
                        <div class="col-md-6"><label class="form-label text-muted small mb-1">Status</label><p class="mb-0"><span class="badge bg-{{ $customer->status->badgeClass() }}">{{ $customer->status->label() }}</span></p></div>
                        <div class="col-12"><label class="form-label text-muted small mb-1">Address</label><p class="mb-0 fw-semibold">{{ $customer->address ?? '-' }}</p></div>
                        <div class="col-12"><label class="form-label text-muted small mb-1">Notes</label><p class="mb-0 text-muted">{{ $customer->notes ?? '-' }}</p></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Account Details</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label text-muted small mb-1">Credit Limit</label><p class="mb-0 fw-semibold">{{ number_format($customer->credit_limit, 2) }} EGP</p></div>
                    <div class="mb-3"><label class="form-label text-muted small mb-1">Created</label><p class="mb-0 small">{{ $customer->created_at->format('M d, Y H:i') }}</p></div>
                    <div><label class="form-label text-muted small mb-1">Last Updated</label><p class="mb-0 small">{{ $customer->updated_at->format('M d, Y H:i') }}</p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent Transactions</h5>
            <a href="{{ route('customer-ledger.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">View Statement</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Reference</th><th>Type</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Running Balance</th></tr></thead>
                    <tbody>
                        @forelse ($recentLedgerEntries as $entry)
                            <tr>
                                <td>{{ $entry->transaction_date->format('M d, Y') }}</td>
                                <td><code>{{ $entry->reference_number ?? '-' }}</code></td>
                                <td>{{ $entry->reference_type->label() }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}</td>
                                <td class="text-end">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($entry->running_balance, 2) }} EGP</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-journal-text display-6 d-block mb-2"></i>No ledger entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent Sales Invoices</h5>
            <a href="{{ route('sales-invoices.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">View All Invoices</a>
        </div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Invoice #</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse ($recentSalesInvoices as $invoice)
                    <tr><td><a href="{{ route('sales-invoices.show', $invoice) }}" class="text-decoration-none fw-semibold">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->invoice_date->format('M d, Y') }}</td><td><span class="badge bg-{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td><td class="text-end">{{ number_format($invoice->total_amount, 2) }} EGP</td><td class="text-end"><a href="{{ route('sales-invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-receipt display-6 d-block mb-2"></i>No sales invoices found.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent Payment Receipts</h5>
            <a href="{{ route('payment-receipts.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">View All Payments</a>
        </div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Receipt #</th><th>Date</th><th>Payment Method</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse ($recentPaymentReceipts as $receipt)
                    <tr><td><a href="{{ route('payment-receipts.show', $receipt) }}" class="text-decoration-none fw-semibold">{{ $receipt->receipt_number }}</a></td><td>{{ $receipt->payment_date->format('M d, Y') }}</td><td>{{ $receipt->payment_method->label() }}</td><td class="text-end">{{ number_format($receipt->amount, 2) }} EGP</td><td><span class="badge bg-{{ $receipt->status->badgeClass() }}">{{ $receipt->status->label() }}</span></td><td class="text-end"><a href="{{ route('payment-receipts.show', $receipt) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-cash-coin display-6 d-block mb-2"></i>No payment receipts found.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="mt-3">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Customers</a>
        <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Delete</button>
        </form>
    </div>
@endsection
