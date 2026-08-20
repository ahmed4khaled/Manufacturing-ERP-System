@extends('layouts.dashboard')

@section('title', 'تعديل ' . $customer->name . ' - ' . config('app.name'))

@section('breadcrumb')
    <nav aria-label="مسار التنقل">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">لوحة التحكم</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customers.index') }}" class="text-decoration-none">العملاء</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customers.show', $customer) }}" class="text-decoration-none">{{ $customer->code }}</a></li>
            <li class="breadcrumb-item active">تعديل</li>
        </ol>
    </nav>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">تعديل العميل</h1>
        <p class="text-muted mb-0">{{ $customer->code }} — {{ $customer->name }}</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">بيانات العميل</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('customers.update', $customer) }}">
                        @csrf
                        @method('PUT')

                        @include('customers.partials.form', ['customer' => $customer])

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> تحديث العميل
                            </button>
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
