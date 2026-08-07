@extends('layouts.dashboard')

@section('title', $size->name)

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-0">{{ $size->name }}</h1><span class="badge bg-{{ $size->status->badgeClass() }}">{{ $size->status->label() }}</span></div>
        <div class="d-flex gap-2">
            <a href="{{ route('sizes.edit', $size) }}" class="btn btn-primary">Edit</a>
            <form action="{{ route('sizes.destroy', $size) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        </div>
    </div>
    <div class="card"><div class="card-body">
        <p><strong>Sort Order:</strong> {{ $size->sort_order }}</p>
        <p class="mb-0"><strong>Variants:</strong> {{ $size->variants_count }}</p>
    </div></div>
    <div class="mt-3"><a href="{{ route('sizes.index') }}" class="btn btn-outline-secondary">Back</a></div>
@endsection
