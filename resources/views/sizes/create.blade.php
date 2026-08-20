@extends('layouts.dashboard')

@section('title', 'إنشاء مقاس')

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">إنشاء مقاس</h1></div>
    <div class="row"><div class="col-lg-8"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('sizes.store') }}">@csrf
            @include('sizes.partials.form')
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">حفظ المقاس</button><a href="{{ route('sizes.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
        </form>
    </div></div></div></div>
@endsection
