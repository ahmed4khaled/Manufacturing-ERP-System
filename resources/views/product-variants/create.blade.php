@extends('layouts.dashboard')

@section('title', 'Create Variant')

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">Create Product Variant</h1><p class="text-muted mb-0">Select product, color, and size — SKU and barcode are generated automatically</p></div>
    <div class="row"><div class="col-lg-10"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('product-variants.store') }}">@csrf
            @include('product-variants.partials.form', compact('products', 'colors', 'sizes'))
            <div class="alert alert-light border mt-3 mb-0 small">
                <i class="bi bi-info-circle me-1"></i>
                SKU format: <code>ProductCode-ColorCode-Size</code> (e.g. <code>PRD000021-BLK-56</code>).
                Barcode is a unique system number assigned at creation.
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Save Variant</button><a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div></div></div></div>
@endsection
