@php
    $editing = isset($managedRole);
    $protected = $editing && $managedRole->name === 'Super Admin';
    $selectedPermissions = collect(old('permission_ids', $editing ? $managedRole->permissions->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
@endphp
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="mb-3"><label class="form-label">اسم الدور</label><input name="name" class="form-control" value="{{ old('name', $managedRole->name ?? '') }}" {{ $protected ? 'readonly' : '' }} required></div>
@if($protected)<div class="alert alert-info">دور Super Admin محمي ويحصل دائمًا على كل الصلاحيات الحالية والجديدة، بالإضافة إلى تجاوز Gate.</div>@endif
<div class="accordion" id="role-permissions">@foreach($permissionGroups as $group => $permissions)<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#role-permission-{{ $loop->index }}">{{ $group }} <span class="badge bg-light text-dark ms-2">{{ $permissions->count() }}</span></button></h2><div id="role-permission-{{ $loop->index }}" class="accordion-collapse collapse"><div class="accordion-body row g-2">@foreach($permissions as $permission)<div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($protected || $selectedPermissions->contains((string) $permission->id)) @disabled($protected)><span class="form-check-label" dir="ltr">{{ $permission->name }}</span></label></div>@endforeach</div></div></div>@endforeach</div>
<div class="mt-4"><button class="btn btn-primary">حفظ</button><a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
