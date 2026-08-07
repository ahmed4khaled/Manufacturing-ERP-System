@extends('layouts.dashboard')

@section('title', $salesInvoice->invoice_number . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sales-invoices.index') }}" class="text-decoration-none">Sales Invoices</a></li>
            <li class="breadcrumb-item active">{{ $salesInvoice->invoice_number }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $salesInvoice->invoice_number }}</h1>
                <span class="badge bg-{{ $salesInvoice->status->badgeClass() }}">{{ $salesInvoice->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $salesInvoice->customer?->name }} · {{ $salesInvoice->invoice_date->format('M d, Y') }}</p>
        </div>
        <div class="d-flex gap-2">
            @if ($salesInvoice->status === \App\Enums\SalesInvoiceStatus::DRAFT)
                <a href="{{ route('sales-invoices.edit', $salesInvoice) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
            @endif
            <form action="{{ route('sales-invoices.destroy', $salesInvoice) }}" method="POST" onsubmit="return confirm('Delete this invoice?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Delete</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Customer</h5></div>
                <div class="card-body">
                    <p class="fw-semibold mb-1">{{ $salesInvoice->customer?->name }}</p>
                    <p class="text-muted small mb-1">{{ $salesInvoice->customer?->code }}</p>
                    <p class="text-muted small mb-0">{{ $salesInvoice->customer?->phone }}</p>
                </div>
            </div>

            @if ($salesInvoice->notes)
                <div class="card mt-4">
                    <div class="card-header bg-white"><h5 class="card-title mb-0">Notes</h5></div>
                    <div class="card-body"><p class="mb-0 text-muted">{{ $salesInvoice->notes }}</p></div>
                </div>
            @endif

            <div class="card mt-4">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Totals</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Quantity</span>
                        <strong>{{ number_format($salesInvoice->total_quantity) }} Pieces</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Invoice Total</span>
                        <strong class="fs-5 text-primary">{{ number_format($salesInvoice->total_amount, 2) }} EGP</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Invoice Items (by Model)</h5></div>
                <div class="card-body p-0">
                    @foreach ($groupedItems as $group)
                        <div class="border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-0">{{ $group['product_name'] }}</h6>
                                </div>
                                <div class="text-end">
                                    <strong>{{ number_format($group['total_quantity']) }} Pieces</strong><br>
                                    <span class="text-primary fw-semibold">{{ number_format($group['total_amount'], 2) }} EGP</span>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th>SKU</th>
                                            <th>Barcode</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($group['items'] as $item)
                                            <tr>
                                                <td>{{ $item->color_name }}</td>
                                                <td>{{ $item->size_name }}</td>
                                                <td><code>{{ $item->sku }}</code></td>
                                                <td>{{ $item->barcode ?? '—' }}</td>
                                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                                <td class="text-end">{{ $item->quantity }}</td>
                                                <td class="text-end">{{ number_format($item->line_total, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('sales-invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Invoices</a>
    </div>
@endsection
