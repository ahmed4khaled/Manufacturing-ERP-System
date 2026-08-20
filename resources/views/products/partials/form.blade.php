@props(['product' => null, 'categories' => []])

<div class="row g-3">
    <div class="col-md-6">
        <label for="category_id" class="form-label">التصنيف <span class="text-danger">*</span></label>
        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">اختر التصنيف</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>
                    {{ $category->code }} — {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">اسم الموديل <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product?->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">الوصف</label>
        <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product?->description) }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="cost_price" class="form-label">سعر التكلفة</label>
        <div class="input-group">
            <span class="input-group-text">ج.م</span>
            <input type="number" name="cost_price" id="cost_price" step="0.01" min="0" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price', $product?->cost_price ?? '0.00') }}">
            @error('cost_price')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @if ($product)
        <div class="col-md-6">
            <label for="status" class="form-label">الحالة <span class="text-danger">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (\App\Enums\ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $product->status->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
