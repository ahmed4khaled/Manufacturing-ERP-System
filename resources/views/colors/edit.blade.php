@extends('layouts.dashboard')

@section('title', 'Edit ' . $color->name)

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">Edit Color</h1><p class="text-muted mb-0">{{ $color->name }}</p></div>
    <div class="row"><div class="col-lg-8">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('colors.update', $color) }}">@csrf @method('PUT')
                @include('colors.partials.form', ['color' => $color])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Update Color</button>
                    <a href="{{ route('colors.show', $color) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
@endsection
