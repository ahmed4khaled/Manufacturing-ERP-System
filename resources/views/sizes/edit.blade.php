@extends('layouts.dashboard')

@section('title', 'Edit ' . $size->name)

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">Edit Size</h1></div>
    <div class="row"><div class="col-lg-8"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('sizes.update', $size) }}">@csrf @method('PUT')
            @include('sizes.partials.form', ['size' => $size])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Update Size</button><a href="{{ route('sizes.show', $size) }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div></div></div></div>
@endsection
