@extends('layouts.dashboard')

@section('title', 'Sizes - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">Sizes</h1><p class="text-muted mb-0">Manage abaya size options</p></div>
        <a href="{{ route('sizes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Size</a>
    </div>
    <div class="card"><div class="card-body">
        @include('partials.list-filters', ['action' => route('sizes.index'), 'placeholder' => 'Search by name...', 'statusEnum' => \App\Enums\SizeStatus::class])
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Name</th><th>Sort Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($sizes as $size)
                        <tr>
                            <td><a href="{{ route('sizes.show', $size) }}" class="text-decoration-none fw-semibold">{{ $size->name }}</a></td>
                            <td>{{ $size->sort_order }}</td>
                            <td><span class="badge bg-{{ $size->status->badgeClass() }}">{{ $size->status->label() }}</span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('sizes.show', $size) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('sizes.edit', $size) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('sizes.destroy', $size) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-5">No sizes found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sizes->hasPages())<div class="d-flex justify-content-center mt-4">{{ $sizes->links('pagination::bootstrap-5') }}</div>@endif
    </div></div>
@endsection
