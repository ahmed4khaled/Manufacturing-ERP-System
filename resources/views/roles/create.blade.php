@extends('layouts.dashboard')
@section('title', 'دور جديد')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('roles.index') }}">الأدوار</a></li><li class="breadcrumb-item active">جديد</li></ol>@endsection
@section('content')<div class="card"><div class="card-header bg-white"><h5 class="mb-0">إنشاء دور</h5></div><div class="card-body"><form method="POST" action="{{ route('roles.store') }}">@csrf @include('roles.partials.form')</form></div></div>@endsection
