@props(['color' => null])

<div class="row g-3">
    <div class="col-md-4">
        <label for="code" class="form-label">Color Code <span class="text-danger">*</span></label>
        <input type="text" name="code" id="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $color?->code) }}" maxlength="10" placeholder="BLK" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Short unique code used in variant SKUs (e.g. BLK, NAV).</div>
    </div>

    <div class="col-md-4">
        <label for="name" class="form-label">Color Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $color?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="hex_code" class="form-label">Hex Code</label>
        <div class="input-group">
            <input type="color" class="form-control form-control-color" id="hex_picker" value="{{ old('hex_code', $color?->hex_code ?? '#000000') }}" oninput="document.getElementById('hex_code').value = this.value">
            <input type="text" name="hex_code" id="hex_code" class="form-control @error('hex_code') is-invalid @enderror" value="{{ old('hex_code', $color?->hex_code) }}" placeholder="#000000">
            @error('hex_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @if ($color)
        <div class="col-md-6">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (\App\Enums\ColorStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $color->status->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
</div>
