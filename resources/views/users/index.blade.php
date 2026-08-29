@extends('layouts.dashboard')
@section('title', 'إدارة المستخدمين')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item active">المستخدمون</li></ol>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">المستخدمون</h4><div class="text-muted small">إدارة الحسابات والأدوار والصلاحيات المباشرة.</div></div>
    @can('users.create')<a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> مستخدم جديد</a>@endcan
</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2">
    <div class="col-md-7"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="بحث بالاسم أو البريد الإلكتروني"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">كل الحالات</option><option value="active" @selected(request('status') === 'active')>نشط</option><option value="inactive" @selected(request('status') === 'inactive')>معطّل</option></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">بحث</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>المستخدم</th><th>الحالة</th><th>الأدوار</th><th>صلاحيات مباشرة</th><th>آخر دخول</th><th>تاريخ الإنشاء</th><th></th></tr></thead>
    <tbody>@forelse($users as $managedUser)<tr>
        <td><div class="fw-semibold">{{ $managedUser->name }}</div><div class="small text-muted" dir="ltr">{{ $managedUser->email }}</div></td>
        <td><span class="badge bg-{{ $managedUser->is_active ? 'success' : 'secondary' }}">{{ $managedUser->is_active ? 'نشط' : 'معطّل' }}</span></td>
        <td>@forelse($managedUser->roles as $role)<span class="badge {{ $role->name === 'Super Admin' ? 'bg-dark' : 'bg-primary' }} me-1">{{ $role->name }}</span>@empty<span class="text-muted">بدون دور</span>@endforelse</td>
        <td><span class="badge bg-light text-dark">{{ $managedUser->permissions->count() }}</span></td>
        <td>{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? '—' }}</td>
        <td>{{ $managedUser->created_at?->format('Y-m-d') ?? '—' }}</td>
        <td class="text-nowrap">
            @can('users.edit')<a href="{{ route('users.edit', $managedUser) }}" class="btn btn-sm btn-outline-primary">تعديل</a>@endcan
            @can('users.status')
                <form method="POST" action="{{ route('users.status', $managedUser) }}" class="d-inline">@csrf @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $managedUser->is_active ? 0 : 1 }}">
                    <button class="btn btn-sm btn-outline-{{ $managedUser->is_active ? 'danger' : 'success' }}" onclick="return confirm('هل أنت متأكد؟')">{{ $managedUser->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                </form>
            @endcan
        </td>
    </tr>@empty<tr><td colspan="7" class="text-center text-muted py-5">لا يوجد مستخدمون مطابقون.</td></tr>@endforelse</tbody>
</table></div>@if($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif</div>
@endsection
