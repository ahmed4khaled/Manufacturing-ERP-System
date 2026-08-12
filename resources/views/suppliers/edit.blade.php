@extends('layouts.dashboard')
@section('title','Edit Supplier')
@section('content')
<div class="page-header"><h1 class="h3 mb-1">Edit Supplier</h1><p class="text-muted mb-0">{{ $supplier->code }} · {{ $supplier->name }}</p></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('suppliers.update',$supplier) }}">@csrf @method('PUT') @include('suppliers.partials.form',['supplier'=>$supplier])<div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Save Changes</button><a href="{{ route('suppliers.show',$supplier) }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div>
@endsection
