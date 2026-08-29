@extends('layouts.dashboard')
@section('title', 'إدارة الأدوار')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item active">الأدوار</li></ol>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">الأدوار</h4><div class="small text-muted">كل دور يجمع مجموعة من صلاحيات النظام الموجودة.</div></div>@can('roles.create')<a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> دور جديد</a>@endcan</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>الدور</th><th>المستخدمون</th><th>الصلاحيات</th><th>تاريخ الإنشاء</th><th></th></tr></thead><tbody>
@foreach($roles as $role)<tr><td class="fw-semibold">{{ $role->name }} @if($role->name === 'Super Admin')<span class="badge bg-dark">محمي</span>@endif</td><td>{{ $role->users_count }}</td><td>{{ $role->permissions_count }}</td><td>{{ $role->created_at?->format('Y-m-d') }}</td><td class="text-nowrap">@can('roles.edit')<a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">تعديل</a>@endcan @can('roles.delete')@if($role->users_count === 0 && $role->name !== 'Super Admin')<form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف الدور غير المستخدم؟')">حذف</button></form>@endif @endcan</td></tr>
@endforeach</tbody></table></div></div>
@endsection
