@extends('layouts.dashboard')
@section('title', $supplier->name . ' - Supplier')
@section('content')
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">{{ $supplier->name }} <span
                    class="badge bg-{{ $supplier->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($supplier->status) }}</span>
            </h1>
            <p class="text-muted mb-0">{{ $supplier->code }}</p>
        </div>
       <div class="d-flex gap-2">
    <a href="{{ route('suppliers.ledger', $supplier) }}" class="btn btn-outline-primary">
        <i class="bi bi-journal-text me-1"></i>
        Supplier Ledger
    </a>

    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i>
        Edit
    </a>

    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
        onsubmit="return confirm('Delete this supplier?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">
            Delete
        </button>
    </form>
</div>
    </div>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Supplier Details</h5>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><small class="text-muted">Phone</small>
                        <div>{{ $supplier->phone ?? '—' }}</div>
                    </div>
                    <div class="col-md-6"><small class="text-muted">Email</small>
                        <div>{{ $supplier->email ?? '—' }}</div>
                    </div>
                    <div class="col-md-6"><small class="text-muted">Tax Number</small>
                        <div>{{ $supplier->tax_number ?? '—' }}</div>
                    </div>
                    <div class="col-12"><small class="text-muted">Address</small>
                        <div>{{ $supplier->address ?? '—' }}</div>
                    </div>
                    <div class="col-12"><small class="text-muted">Notes</small>
                        <div>{{ $supplier->notes ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Contacts</h5>
                </div>
                <div class="card-body">
                    <form class="row g-2 mb-4" method="POST" action="{{ route('suppliers.contacts.store', $supplier) }}">
                        @csrf <div class="col-md-4"><input required name="name" placeholder="Contact name"
                                class="form-control"></div>
                        <div class="col-md-3"><input name="job_title" placeholder="Job title" class="form-control"></div>
                        <div class="col-md-2"><input name="phone" placeholder="Phone" class="form-control"></div>
                        <div class="col-md-2"><input type="email" name="email" placeholder="Email" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-center"><input type="checkbox" name="is_primary"
                                value="1" title="Primary"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">Add Contact</button></div>
                    </form>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Title</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplier->contacts as $contact)
                                <tr>
                                    <td>{{ $contact->name }} @if ($contact->is_primary)
                                            <span class="badge bg-primary">Primary</span>
                                        @endif
                                    </td>
                                    <td>{{ $contact->job_title }}</td>
                                    <td>{{ $contact->phone }}</td>
                                    <td>{{ $contact->email }}</td>
                                    <td class="text-end">
                                        <form method="POST"
                                            action="{{ route('suppliers.contacts.destroy', [$supplier, $contact]) }}">@csrf
                                            @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i
                                                    class="bi bi-trash"></i></button></form>
                                    </td>
                            </tr>@empty<tr>
                                    <td colspan="5" class="text-center text-muted">No contacts yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Financial Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3"><small class="text-muted">Total Purchases</small>
                        <div class="fw-semibold">{{ number_format($summary['purchases'], 2) }}</div>
                    </div>
                    <div class="mb-3"><small class="text-muted">Total Invoiced</small>
                        <div class="fw-semibold">{{ number_format($summary['invoiced'], 2) }}</div>
                    </div>
                    <div class="mb-3"><small class="text-muted">Total Paid</small>
                        <div class="fw-semibold">{{ number_format($summary['paid'], 2) }}</div>
                    </div>
                    <div><small class="text-muted">Outstanding</small>
                        <div class="fw-bold text-danger">{{ number_format($summary['outstanding'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
