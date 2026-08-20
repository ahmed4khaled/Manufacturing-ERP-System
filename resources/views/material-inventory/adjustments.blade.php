@extends('layouts.dashboard')

@section('title', 'تسوية مخزون الخامات - ' . config('app.name'))

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">تسوية مخزون الخامات</h1>
        <p class="text-muted mb-0">سجّل زيادة أو نقصًا منضبطًا في مخزون الخامات.</p>
    </div>

    <form method="POST" action="{{ route('material-inventory.adjustments.store') }}">
        <div class="card">
            <div class="card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الخامة <span class="text-danger">*</span></label>
                        <select name="material_id" class="form-select @error('material_id') is-invalid @enderror" required>
                            <option value="">اختر الخامة</option>
                            @foreach($materials as $material)
                                <option value="{{ $material->id }}" @selected(old('material_id') == $material->id)>
                                    {{ $material->code }} - {{ $material->name }} ({{ $material->unit->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('material_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">نوع التسوية <span class="text-danger">*</span></label>
                        <select name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                            <option value="in" @selected(old('direction') === 'in')>وارد</option>
                            <option value="out" @selected(old('direction') === 'out')>صادر</option>
                        </select>
                        @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الكمية <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">التاريخ <span class="text-danger">*</span></label>
                        <input type="date" name="movement_date" value="{{ old('movement_date', now()->format('Y-m-d')) }}" class="form-control @error('movement_date') is-invalid @enderror" required>
                        @error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">السبب / الملاحظات <span class="text-danger">*</span></label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" required>{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary">تسجيل تسوية</button>
            <a href="{{ route('material-inventory.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        </div>
    </form>
@endsection
