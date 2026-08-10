@extends('layouts.dashboard')

@section('title', $productVariant->sku)

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $productVariant->sku }}</h1>
                <span class="badge bg-{{ $productVariant->status->badgeClass() }}">{{ $productVariant->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $productVariant->product?->name }} · {{ $productVariant->color?->name }} · {{ $productVariant->size?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('product-variants.edit', $productVariant) }}" class="btn btn-primary">Edit</a>
            <form action="{{ route('product-variants.destroy', $productVariant) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">Variant Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="text-muted small">Product</label><p class="fw-semibold mb-0"><a href="{{ route('products.show', $productVariant->product) }}">{{ $productVariant->product?->code }} — {{ $productVariant->product?->name }}</a></p></div>
                        <div class="col-md-6"><label class="text-muted small">Barcode</label><p class="fw-semibold mb-0">{{ $productVariant->barcode ?? '—' }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">Color</label><p class="fw-semibold mb-0">{{ $productVariant->color?->name }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">Size</label><p class="fw-semibold mb-0">{{ $productVariant->size?->name }}</p></div>
                        <div class="col-md-4"><label class="text-muted small">Default Price</label><p class="fw-semibold mb-0">{{ number_format($productVariant->default_price, 2) }} SAR</p></div>
                        <div class="col-md-4"><label class="text-muted small">Current Stock</label><p class="fw-semibold mb-0"><a href="{{ route('inventory.show', $productVariant) }}" class="text-decoration-none">View Inventory</a></p></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <p class="small text-muted mb-1">Created</p><p>{{ $productVariant->created_at->format('M d, Y H:i') }}</p>
                <p class="small text-muted mb-1">Updated</p><p class="mb-0">{{ $productVariant->updated_at->format('M d, Y H:i') }}</p>
            </div></div>
        </div>
    </div>

    <div class="mt-3"><a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">Back to Variants</a></div>
@endsection
