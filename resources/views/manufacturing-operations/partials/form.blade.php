<div class="row g-3">
    <div class="col-md-5"><label class="form-label">اسم العملية *</label><input name="name" class="form-control" value="{{ old('name', $manufacturingOperation->name ?? '') }}" required></div>
    <div class="col-md-3"><label class="form-label">تكلفة العباية الافتراضية *</label><div class="input-group"><input name="default_unit_cost" type="number" min="0" step="0.01" class="form-control" value="{{ old('default_unit_cost', $manufacturingOperation->default_unit_cost ?? '0.00') }}" required><span class="input-group-text">ج.م</span></div></div>
    <div class="col-md-4"><label class="form-label">الحالة</label><select name="status" class="form-select">@foreach (\App\Enums\ManufacturingOperationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', isset($manufacturingOperation) ? $manufacturingOperation->status->value : 'active') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label">الوصف</label><textarea name="description" class="form-control">{{ old('description', $manufacturingOperation->description ?? '') }}</textarea></div>
</div>
<div class="mt-3"><button class="btn btn-primary">حفظ</button> <a class="btn btn-outline-secondary" href="{{ route('manufacturing-operations.index') }}">إلغاء</a></div>
