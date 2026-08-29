@extends('layouts.dashboard') @section('title', 'عملية تصنيع جديدة') @section('content')<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">عملية تصنيع جديدة</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('manufacturing-operations.store') }}">@csrf @include('manufacturing-operations.partials.form')
        </form>
    </div>
</div>@endsection
