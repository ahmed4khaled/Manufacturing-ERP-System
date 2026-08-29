@extends('layouts.dashboard')
@section('title', 'تعديل المستخدم')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('users.index') }}">المستخدمون</a></li><li class="breadcrumb-item active">تعديل</li></ol>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="card-header bg-white d-flex justify-content-between"><h5 class="mb-0">تعديل {{ $managedUser->name }}</h5>@if($managedUser->hasRole('Super Admin'))<span class="badge bg-dark">Super Admin محمي</span>@endif</div><div class="card-body"><form method="POST" action="{{ route('users.update', $managedUser) }}">@csrf @method('PUT') @include('users.partials.form')</form></div></div>
@endsection
