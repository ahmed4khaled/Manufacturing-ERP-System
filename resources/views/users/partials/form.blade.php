@php
    $editing = isset($managedUser);
    $selectedRoles = collect(old('role_ids', $editing ? $managedUser->roles->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
    $selectedPermissions = collect(old('permission_ids', $editing ? $managedUser->permissions->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
@endphp
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">الاسم</label><input name="name" class="form-control" value="{{ old('name', $managedUser->name ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" dir="ltr" class="form-control" value="{{ old('email', $managedUser->email ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">كلمة المرور {{ $editing ? '(اتركها فارغة للاحتفاظ بالحالية)' : '' }}</label><input type="password" name="password" class="form-control" autocomplete="new-password" {{ $editing ? '' : 'required' }}></div>
    <div class="col-md-6"><label class="form-label">تأكيد كلمة المرور</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" {{ $editing ? '' : 'required' }}></div>
    <div class="col-md-4"><label class="form-label">الحالة</label><select name="is_active" class="form-select"><option value="1" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '1')>نشط</option><option value="0" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '0')>معطّل</option></select></div>
</div>
@can('users.assign_roles')
<input type="hidden" name="roles_submitted" value="1">
<hr><h6>الأدوار</h6><div class="row g-2">
    @foreach($roles as $role)<div class="col-md-4"><label class="border rounded p-2 w-100"><input type="checkbox" class="form-check-input me-1" name="role_ids[]" value="{{ $role->id }}" @checked($selectedRoles->contains((string) $role->id))> {{ $role->name }}</label></div>@endforeach
</div>
@endcan
@can('users.assign_permissions')
<input type="hidden" name="permissions_submitted" value="1">
<hr><div class="d-flex justify-content-between"><h6>الصلاحيات المباشرة</h6><span class="small text-muted">تُضاف إلى صلاحيات الأدوار ولا تستبدلها.</span></div>
<div class="accordion" id="user-permissions">
    @foreach($permissionGroups as $group => $permissions)<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#user-permission-{{ $loop->index }}">{{ $group }} <span class="badge bg-light text-dark ms-2">{{ $permissions->count() }}</span></button></h2>
        <div id="user-permission-{{ $loop->index }}" class="accordion-collapse collapse"><div class="accordion-body row g-2">@foreach($permissions as $permission)<div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($selectedPermissions->contains((string) $permission->id))><span class="form-check-label" dir="ltr">{{ $permission->name }}</span></label></div>@endforeach</div></div>
    </div>@endforeach
</div>
@endcan
<div class="mt-4"><button class="btn btn-primary">حفظ</button><a href="{{ route('users.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
