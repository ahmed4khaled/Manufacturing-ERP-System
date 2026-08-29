@extends('layouts.dashboard')
@section('title', 'مستخدم جديد')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('users.index') }}">المستخدمون</a></li><li class="breadcrumb-item active">جديد</li></ol>@endsection
@section('content')<div class="card"><div class="card-header bg-white"><h5 class="mb-0">إنشاء مستخدم</h5></div><div class="card-body"><form method="POST" action="{{ route('users.store') }}">@csrf @include('users.partials.form')</form></div></div>@endsection
