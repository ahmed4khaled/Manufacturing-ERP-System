@extends('layouts.dashboard')

@section('title', 'Dashboard - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, {{ Auth::user()->name }}</p>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="bi bi-people fs-4 text-primary"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-0">Customers</p>
                            <h4 class="mb-0">{{ \App\Models\Customer::count() }}</h4>
                        </div>
                    </div>
                    <a href="{{ route('customers.index') }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-box-seam fs-4 text-success"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-0">Products</p>
                            <h4 class="mb-0">{{ \App\Models\Product::count() }}</h4>
                        </div>
                    </div>
                    <a href="{{ route('products.index') }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="bi bi-layers fs-4 text-info"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-0">Variants</p>
                            <h4 class="mb-0">{{ \App\Models\ProductVariant::count() }}</h4>
                        </div>
                    </div>
                    <a href="{{ route('product-variants.index') }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card opacity-50">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3">
                            <i class="bi bi-boxes fs-4 text-secondary"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-0">Inventory</p>
                            <h4 class="mb-0">—</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Customer</a>
                <a href="{{ route('products.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i> New Product</a>
                <a href="{{ route('product-variants.create') }}" class="btn btn-outline-primary"><i class="bi bi-layers me-1"></i> New Variant</a>
            </div>
        </div>
    </div>
@endsection
