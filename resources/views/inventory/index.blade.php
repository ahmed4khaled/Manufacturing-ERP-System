@extends('layouts.dashboard')

@section('title', 'Inventory - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">Inventory</h1><p class="text-muted mb-0">Current stock by product variant</p></div>
        <div class="d-flex gap-2"><a href="{{ route('inventory.opening-stock.create') }}" class="btn btn-outline-primary">Opening Stock</a><a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary">Stock Adjustment</a></div>
    </div>
    <div class="card"><div class="card-body">
        <form method="GET" class="row g-3 mb-4"><div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Product, SKU, barcode, color, size..."></div></div><div class="col-md-3"><select name="product_id" class="form-select"><option value="">All Products</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->code }} - {{ $product->name }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-primary">Filter</button>@if(request()->hasAny(['search','product_id'])) <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Clear</a>@endif</div></form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Product</th><th>Color</th><th>Size</th><th>SKU</th><th>Barcode</th><th class="text-end">Current Stock</th><th>Status</th></tr></thead><tbody>@forelse($variants as $variant)<tr><td>{{ $variant->product?->code }} - {{ $variant->product?->name }}</td><td>{{ $variant->color?->name }}</td><td>{{ $variant->size?->name }}</td><td><a href="{{ route('inventory.show', $variant) }}" class="text-decoration-none fw-semibold">{{ $variant->sku }}</a></td><td>{{ $variant->barcode ?? '-' }}</td><td class="text-end fw-semibold">{{ $variant->current_stock }}</td><td><span class="badge bg-{{ $variant->current_stock > 0 ? 'success' : 'secondary' }}">{{ $variant->current_stock > 0 ? 'In Stock' : 'Out of Stock' }}</span></td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-5">No variants found.</td></tr>@endforelse</tbody></table></div>
        @if($variants->hasPages())<div class="d-flex justify-content-center mt-4">{{ $variants->links('pagination::bootstrap-5') }}</div>@endif
    </div></div>
@endsection
