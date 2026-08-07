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
                        <p class="mb-0 fw-semibold fs-5 text-primary">{{ number_format($customer->credit_limit, 2) }} SAR</p>
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
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Customers
        </a>
    </div>
@endsection
