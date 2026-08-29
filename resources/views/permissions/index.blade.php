@extends('layouts.dashboard')
@section('title', 'الصلاحيات')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item active">الصلاحيات</li></ol>@endsection
@section('content')
<div class="mb-3"><h4 class="mb-1">الصلاحيات</h4><div class="text-muted small">عرض مرجعي لـ {{ $permissionCount }} صلاحية فعلية مسجلة في النظام. التعيين يتم من صفحات المستخدمين والأدوار.</div></div>
<div class="row g-3">@foreach($permissionGroups as $group => $permissions)<div class="col-xl-4 col-md-6"><div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><strong>{{ $group }}</strong><span class="badge bg-light text-dark">{{ $permissions->count() }}</span></div><div class="list-group list-group-flush">@foreach($permissions as $permission)<div class="list-group-item d-flex justify-content-between align-items-center"><code dir="ltr">{{ $permission->name }}</code><span class="small text-muted">{{ $permission->roles_count }} دور</span></div>@endforeach</div></div></div>@endforeach</div>
@endsection
