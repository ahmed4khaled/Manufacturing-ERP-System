@extends('layouts.dashboard')

@section('title', 'Create Color - ' . config('app.name'))

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">Create Color</h1></div>
    <div class="row"><div class="col-lg-8">
        <div class="card"><div class="card-header bg-white"><h5 class="card-title mb-0">Color Information</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('colors.store') }}">@csrf
                    @include('colors.partials.form')
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Save Color</button>
                        <a href="{{ route('colors.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div></div>
@endsection
