@extends('layouts.dashboard')

@section('title', 'Variants - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">Product Variants</h1><p class="text-muted mb-0">Color and size combinations with SKU, price, and quantity</p></div>
        <a href="{{ route('product-variants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Variant</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" action="{{ route('product-variants.index') }}" class="row g-3 mb-4">
            <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search SKU, barcode..." value="{{ request('search') }}">
            </div></div>
            <div class="col-md-3">
                <select name="product_id" class="form-select">
                    <option value="">All Products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->code }} — {{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\VariantStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if (request()->hasAny(['search', 'status', 'product_id']))<a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">Clear</a>@endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>SKU</th><th>Product</th><th>Color</th><th>Size</th><th>Status</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($variants as $variant)
                        <tr>
                            <td><a href="{{ route('product-variants.show', $variant) }}" class="text-decoration-none fw-semibold">{{ $variant->sku }}</a></td>
                            <td>{{ $variant->product?->name }}</td>
                            <td>{{ $variant->color?->name }}</td>
                            <td>{{ $variant->size?->name }}</td>
                            <td><span class="badge bg-{{ $variant->status->badgeClass() }}">{{ $variant->status->label() }}</span></td>
                            <td class="text-end">{{ number_format($variant->default_price, 2) }}</td>
                            <td class="text-end">{{ $variant->quantity }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('product-variants.show', $variant) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('product-variants.edit', $variant) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('product-variants.destroy', $variant) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No variants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($variants->hasPages())<div class="d-flex justify-content-center mt-4">{{ $variants->links('pagination::bootstrap-5') }}</div>@endif
    </div></div>
@endsection
