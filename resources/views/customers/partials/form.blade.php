@props(['customer' => null])

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">الاسم <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $customer?->name) }}"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">الهاتف <span class="text-danger">*</span></label>
        <input
            type="text"
            name="phone"
            id="phone"
            class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $customer?->phone) }}"
            required
        >
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="phone_2" class="form-label">هاتف إضافي</label>
        <input
            type="text"
            name="phone_2"
            id="phone_2"
            class="form-control @error('phone_2') is-invalid @enderror"
            value="{{ old('phone_2', $customer?->phone_2) }}"
        >
        @error('phone_2')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="city" class="form-label">المدينة</label>
        <input
            type="text"
            name="city"
            id="city"
            class="form-control @error('city') is-invalid @enderror"
            value="{{ old('city', $customer?->city) }}"
        >
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label">العنوان</label>
        <textarea
            name="address"
            id="address"
            rows="2"
            class="form-control @error('address') is-invalid @enderror"
        >{{ old('address', $customer?->address) }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="credit_limit" class="form-label">حد الائتمان</label>
        <div class="input-group">
            <span class="input-group-text">ج.م</span>
            <input
                type="number"
                name="credit_limit"
                id="credit_limit"
                step="0.01"
                min="0"
                class="form-control @error('credit_limit') is-invalid @enderror"
                value="{{ old('credit_limit', $customer?->credit_limit ?? '0.00') }}"
            >
            @error('credit_limit')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @if ($customer)
        <div class="col-md-6">
            <label for="status" class="form-label">الحالة <span class="text-danger">*</span></label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (\App\Enums\CustomerStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $customer->status->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-12">
        <label for="notes" class="form-label">ملاحظات</label>
        <textarea
            name="notes"
            id="notes"
            rows="3"
            class="form-control @error('notes') is-invalid @enderror"
        >{{ old('notes', $customer?->notes) }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
