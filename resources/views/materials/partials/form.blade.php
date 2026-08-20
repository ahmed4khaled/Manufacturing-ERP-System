@props(['material' => null])

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">الاسم <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $material?->name) }}"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="category" class="form-label">التصنيف <span class="text-danger">*</span></label>
        <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
            <option value="">اختر التصنيف</option>
            @foreach (\App\Enums\MaterialCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected(old('category', $material?->category?->value) === $category->value)>
                    {{ $category->label() }}
                </option>
            @endforeach
        </select>
        @error('category')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="unit" class="form-label">وحدة القياس <span class="text-danger">*</span></label>
        <select name="unit" id="unit" class="form-select @error('unit') is-invalid @enderror" required>
            <option value="">اختر الوحدة</option>
            @foreach (\App\Enums\MaterialUnit::cases() as $unit)
                <option value="{{ $unit->value }}" @selected(old('unit', $material?->unit?->value) === $unit->value)>
                    {{ $unit->label() }}
                </option>
            @endforeach
        </select>
        @error('unit')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="minimum_stock" class="form-label">الحد الأدنى / حد إعادة الطلب</label>
        <input
            type="number"
            name="minimum_stock"
            id="minimum_stock"
            step="0.001"
            min="0"
            class="form-control @error('minimum_stock') is-invalid @enderror"
            value="{{ old('minimum_stock', $material?->minimum_stock ?? 0) }}"
        >
        @error('minimum_stock')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">الوصف</label>
        <textarea
            name="description"
            id="description"
            rows="3"
            class="form-control @error('description') is-invalid @enderror"
        >{{ old('description', $material?->description) }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if ($material)
        <div class="col-md-6">
            <label for="status" class="form-label">الحالة <span class="text-danger">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (\App\Enums\MaterialStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $material->status->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
