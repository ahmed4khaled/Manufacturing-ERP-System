@extends('layouts.dashboard')

@section('title', $product->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none">Products</a></li>
            <li class="breadcrumb-item active">{{ $product->code }}</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">{{ $product->name }}</h1>
                <span class="badge bg-{{ $product->status->badgeClass() }}">{{ $product->status->label() }}</span>
            </div>
            <p class="text-muted mb-0">{{ $product->code }} · {{ $product->category?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('product-variants.create', ['product_id' => $product->id]) }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Add Variant</a>
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Delete this product?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Delete</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Description</label>
                            <p class="mb-0">{{ $product->description ?? '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Cost Price</label>
                            <p class="mb-0 fw-semibold">{{ number_format($product->cost_price, 2) }} SAR</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Variants</h5>
                    <span class="badge bg-secondary">{{ $product->variants->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if ($product->variants->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">No variants yet. <a href="{{ route('product-variants.create') }}">Create one</a>.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Color</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->variants as $variant)
                                        <tr>
                                            <td><a href="{{ route('product-variants.show', $variant) }}" class="text-decoration-none">{{ $variant->sku }}</a></td>
                                            <td>
                                                @if ($variant->color?->hex_code)
                                                    <span class="d-inline-block rounded-circle border" style="width:14px;height:14px;background:{{ $variant->color->hex_code }}"></span>
                                                @endif
                                                {{ $variant->color?->name }}
                                            </td>
                                            <td>{{ $variant->size?->name }}</td>
                                            <td><span class="badge bg-{{ $variant->status->badgeClass() }}">{{ $variant->status->label() }}</span></td>
                                            <td class="text-end">{{ number_format($variant->default_price, 2) }}</td>
                                            <td class="text-end">{{ $variant->quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Summary</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Category</label>
                        <p class="mb-0 fw-semibold">{{ $product->category?->name ?? '—' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Created</label>
                        <p class="mb-0 small">{{ $product->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div>
                        <label class="form-label text-muted small mb-1">Last Updated</label>
                        <p class="mb-0 small">{{ $product->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Products</a>
    </div>
@endsection
