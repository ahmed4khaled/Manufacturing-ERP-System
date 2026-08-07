@extends('layouts.dashboard')

@section('title', 'Categories - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active">Categories</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Categories</h1>
            <p class="text-muted mb-0">Organize abaya models into product categories</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Create Category
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('partials.list-filters', [
                'action' => route('categories.index'),
                'placeholder' => 'Search by code, name...',
                'statusEnum' => \App\Enums\CategoryStatus::class,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <a href="{{ route('categories.show', $category) }}" class="text-decoration-none fw-semibold">
                                        {{ $category->code }}
                                    </a>
                                </td>
                                <td>{{ $category->name }}</td>
                                <td>
                                    <span class="badge bg-{{ $category->status->badgeClass() }}">
                                        {{ $category->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('categories.show', $category) }}" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('categories.edit', $category) }}" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-tags display-6 d-block mb-2"></i>
                                    No categories found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $categories->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
