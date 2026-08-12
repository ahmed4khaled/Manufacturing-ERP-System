@extends('layouts.dashboard')

@section('title', $material->code . ' Material Inventory')

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">{{ $material->name }}</h1>
            <p class="text-muted mb-0">{{ $material->code }} — {{ $material->category->label() }} ({{ $material->unit->label() }})</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('material-inventory.opening-stock.create') }}" class="btn btn-outline-primary">Opening Stock</a>
            <a href="{{ route('material-inventory.adjustments.create') }}" class="btn btn-primary">Adjustment</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Current Stock</p>
                    <p class="mb-0 fw-semibold fs-2 {{ bccomp($currentStock, '0', 3) > 0 ? 'text-success' : 'text-danger' }}">
                        {{ $currentStock }} <span class="fs-6 text-muted">{{ $material->unit->label() }}</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Minimum / Reorder Level</p>
                    <p class="mb-0 fw-semibold">{{ $material->minimum_stock }} {{ $material->unit->label() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Status</p>
                    <p class="mb-0">
                        <span class="badge bg-{{ $material->status->badgeClass() }}">{{ $material->status->label() }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white"><h5 class="card-title mb-0">Stock Movement History</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th class="text-end">Quantity</th>
                            <th>Notes</th>
                            <th class="text-end">Running Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td>{{ $movement->movement_date->format('M d, Y') }}</td>
                                <td><code>{{ $movement->reference_number ?? '-' }}</code></td>
                                <td>{{ $movement->movement_type->label() }}</td>
                                <td>
                                    <span class="badge bg-{{ $movement->direction->badgeClass() }}">
                                        {{ $movement->direction->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    {{ $movement->direction === \App\Enums\StockMovementDirection::IN ? '+' : '-' }}{{ $movement->quantity }}
                                </td>
                                <td>{{ $movement->notes ?? '-' }}</td>
                                <td class="text-end fw-semibold">{{ $movement->running_balance }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No stock movements found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($movements->hasPages())
            <div class="d-flex justify-content-center mt-4 mb-3">
                {{ $movements->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="mt-3">
        <a href="{{ route('material-inventory.index') }}" class="btn btn-outline-secondary">Back to Material Inventory</a>
    </div>
@endsection
