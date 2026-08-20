@props(['variant' => null, 'products' => [], 'colors' => [], 'sizes' => []])

<div class="row g-3">
    <div class="col-md-4">
        <label for="product_id" class="form-label">المنتج <span class="text-danger">*</span></label>
        <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
            <option value="">اختر المنتج</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(old('product_id', $variant?->product_id ?? request('product_id')) == $product->id)>
                    {{ $product->code }} — {{ $product->name }}
                </option>
            @endforeach
        </select>
        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="color_id" class="form-label">اللون <span class="text-danger">*</span></label>
        <select name="color_id" id="color_id" class="form-select @error('color_id') is-invalid @enderror" required>
            <option value="">اختر اللون</option>
            @foreach ($colors as $color)
                <option value="{{ $color->id }}" @selected(old('color_id', $variant?->color_id) == $color->id)>
                    {{ $color->code ?? '' }} — {{ $color->name }}
                </option>
            @endforeach
        </select>
        @error('color_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="size_id" class="form-label">المقاس <span class="text-danger">*</span></label>
        <select name="size_id" id="size_id" class="form-select @error('size_id') is-invalid @enderror" required>
            <option value="">اختر المقاس</option>
            @foreach ($sizes as $size)
                <option value="{{ $size->id }}" @selected(old('size_id', $variant?->size_id) == $size->id)>{{ $size->name }}</option>
            @endforeach
        </select>
        @error('size_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if ($variant)
        <div class="col-md-6">
            <label class="form-label">رمز المخزون</label>
            <input type="text" class="form-control" value="{{ $variant->sku }}" readonly disabled>
            <div class="form-text">يُنشأ تلقائيًا ولا يتغير إلا عند تغيير المنتج أو اللون أو المقاس.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">الباركود</label>
            <input type="text" class="form-control" value="{{ $variant->barcode }}" readonly disabled>
            <div class="form-text">يُعيّن عند الإنشاء ولا يتم تغييره.</div>
        </div>
    @endif

    <div class="col-md-6">
        <label for="default_price" class="form-label">السعر الافتراضي</label>
        <div class="input-group">
            <span class="input-group-text">ج.م</span>
            <input type="number" name="default_price" id="default_price" step="0.01" min="0" class="form-control @error('default_price') is-invalid @enderror" value="{{ old('default_price', $variant?->default_price ?? '0.00') }}">
            @error('default_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @if ($variant)
        <div class="col-md-6">
            <label for="status" class="form-label">الحالة <span class="text-danger">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (\App\Enums\VariantStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $variant->status->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
</div>
