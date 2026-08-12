@extends('layouts.dashboard')

@section('title', 'Material Opening Stock - ' . config('app.name'))

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">Material Opening Stock</h1>
        <p class="text-muted mb-0">Record stock already on hand as a material inventory movement.</p>
    </div>

    <form method="POST" action="{{ route('material-inventory.opening-stock.store') }}">
        <div class="card">
            <div class="card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Material <span class="text-danger">*</span></label>
                        <select name="material_id" class="form-select @error('material_id') is-invalid @enderror" required>
                            <option value="">Select material</option>
                            @foreach($materials as $material)
                                <option value="{{ $material->id }}" @selected(old('material_id') == $material->id)>
                                    {{ $material->code }} - {{ $material->name }} ({{ $material->unit->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('material_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="movement_date" value="{{ old('movement_date', now()->format('Y-m-d')) }}" class="form-control @error('movement_date') is-invalid @enderror" required>
                        @error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary">Record Opening Stock</button>
            <a href="{{ route('material-inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
