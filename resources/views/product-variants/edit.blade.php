@extends('layouts.dashboard')

@section('title', 'Edit ' . $productVariant->sku)

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">Edit Variant</h1><p class="text-muted mb-0">{{ $productVariant->sku }}</p></div>
    <div class="row"><div class="col-lg-10"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('product-variants.update', $productVariant) }}">@csrf @method('PUT')
            @include('product-variants.partials.form', ['variant' => $productVariant, 'products' => $products, 'colors' => $colors, 'sizes' => $sizes])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Update Variant</button><a href="{{ route('product-variants.show', $productVariant) }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div></div></div></div>
@endsection
