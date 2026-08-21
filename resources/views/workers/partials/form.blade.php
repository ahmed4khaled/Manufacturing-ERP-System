@php($editing = isset($worker))
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3">
    @if($editing)<div class="col-md-3"><label class="form-label">الكود</label><input class="form-control" value="{{ $worker->code }}" disabled></div>@endif
    <div class="col-md-{{ $editing ? 5 : 6 }}"><label class="form-label">اسم العامل *</label><input name="name" class="form-control" value="{{ old('name', $worker->name ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label">الهاتف</label><input name="phone" class="form-control" value="{{ old('phone', $worker->phone ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">التخصص</label><input name="specialization" class="form-control" value="{{ old('specialization', $worker->specialization ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">الحالة</label><select name="status" class="form-select">@foreach(\App\Enums\WorkerStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', isset($worker) ? $worker->status->value : 'active') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="3">{{ old('notes', $worker->notes ?? '') }}</textarea></div>
</div>
<div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> حفظ</button> <a href="{{ route('workers.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
