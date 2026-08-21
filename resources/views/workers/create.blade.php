@extends('layouts.dashboard')
@section('title','عامل جديد')
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('workers.index') }}">العمال</a></li><li class="breadcrumb-item active">جديد</li></ol>@endsection
@section('content')<div class="card"><div class="card-header bg-white"><h5 class="mb-0">إضافة عامل</h5></div><div class="card-body"><form method="POST" action="{{ route('workers.store') }}">@csrf @include('workers.partials.form')</form></div></div>@endsection
