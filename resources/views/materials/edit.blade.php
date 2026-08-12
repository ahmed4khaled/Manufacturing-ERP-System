@extends('layouts.dashboard')

@section('title', 'Edit ' . $material->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('materials.index') }}" class="text-decoration-none">Materials</a></li>
            <li class="breadcrumb-item"><a href="{{ route('materials.show', $material) }}" class="text-decoration-none">{{ $material->code }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Edit Material</h1>
        <p class="text-muted mb-0">{{ $material->code }} — {{ $material->name }}</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Material Information</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('materials.update', $material) }}">
                        @csrf
                        @method('PUT')
                        @include('materials.partials.form', ['material' => $material])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Material</button>
                            <a href="{{ route('materials.show', $material) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
