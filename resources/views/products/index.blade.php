@extends('layouts.dashboard')

@section('title', 'Products - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active">Products</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Products</h1>
            <p class="text-muted mb-0">Manage abaya models (e.g. A101)</p>
        </div>
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Product</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('products.index') }}" class="row g-3 mb-4">
                <div class="col-md-4 col-lg-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search code, name..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3 col-lg-2">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\Enums\ProductStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if (request()->hasAny(['search', 'status', 'category_id']))
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="text-end">Cost Price</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td><a href="{{ route('products.show', $product) }}" class="text-decoration-none fw-semibold">{{ $product->code }}</a></td>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->category?->name ?? '—' }}</td>
                                <td><span class="badge bg-{{ $product->status->badgeClass() }}">{{ $product->status->label() }}</span></td>
                                <td class="text-end">{{ number_format($product->cost_price, 2) }} SAR</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-box-seam display-6 d-block mb-2"></i>No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="d-flex justify-content-center mt-4">{{ $products->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
