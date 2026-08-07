@props([
    'action',
    'placeholder' => 'Search...',
    'statusEnum' => null,
    'extraFilters' => null,
])

<form method="GET" action="{{ $action }}" class="row g-3 mb-4">
    <div class="col-md-6 col-lg-4">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="{{ $placeholder }}"
                value="{{ request('search') }}"
            >
        </div>
    </div>

    @if ($statusEnum)
        <div class="col-md-4 col-lg-2">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                @foreach ($statusEnum::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($extraFilters)
        {{ $extraFilters }}
    @endif

    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filter</button>
        @if (request()->hasAny(['search', 'status', 'category_id', 'product_id']))
            <a href="{{ $action }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </div>
</form>
