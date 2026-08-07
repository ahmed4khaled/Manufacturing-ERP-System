@extends('layouts.dashboard')

@section('title', 'Colors - ' . config('app.name'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h3 mb-1">Colors</h1><p class="text-muted mb-0">Manage abaya color options</p></div>
        <a href="{{ route('colors.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Color</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('partials.list-filters', ['action' => route('colors.index'), 'placeholder' => 'Search by code, name, hex...', 'statusEnum' => \App\Enums\ColorStatus::class])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Color</th><th>Code</th><th>Name</th><th>Hex</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($colors as $color)
                            <tr>
                                <td>@if($color->hex_code)<span class="d-inline-block rounded border" style="width:24px;height:24px;background:{{ $color->hex_code }}"></span>@else—@endif</td>
                                <td><code>{{ $color->code }}</code></td>
                                <td><a href="{{ route('colors.show', $color) }}" class="text-decoration-none fw-semibold">{{ $color->name }}</a></td>
                                <td><code>{{ $color->hex_code ?? '—' }}</code></td>
                                <td><span class="badge bg-{{ $color->status->badgeClass() }}">{{ $color->status->label() }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('colors.show', $color) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('colors.edit', $color) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('colors.destroy', $color) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this color?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No colors found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($colors->hasPages())<div class="d-flex justify-content-center mt-4">{{ $colors->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    </div>
@endsection
