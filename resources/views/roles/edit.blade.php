@extends('layouts.dashboard')
@section('title', 'تعديل الدور')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('roles.index') }}">الأدوار</a></li><li class="breadcrumb-item active">تعديل</li></ol>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="card-header bg-white"><h5 class="mb-0">تعديل {{ $managedRole->name }}</h5></div><div class="card-body"><form method="POST" action="{{ route('roles.update', $managedRole) }}">@csrf @method('PUT') @include('roles.partials.form')</form></div></div>
@endsection
