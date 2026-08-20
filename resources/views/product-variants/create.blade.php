@extends('layouts.dashboard')

@section('title', 'إنشاء متغير')

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">إنشاء متغير منتج</h1><p class="text-muted mb-0">اختر المنتج واللون والمقاس، وسيُنشأ رمز المخزون والباركود تلقائيًا</p></div>
    <div class="row"><div class="col-lg-10"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('product-variants.store') }}">@csrf
            @include('product-variants.partials.form', compact('products', 'colors', 'sizes'))
            <div class="alert alert-light border mt-3 mb-0 small">
                <i class="bi bi-info-circle me-1"></i>
                صيغة رمز المخزون: <code>كودالمنتج-كوداللون-المقاس</code> (مثال: <code>PRD000021-BLK-56</code>).
                الباركود رقم فريد يعيّنه النظام عند الإنشاء.
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">حفظ المتغير</button><a href="{{ route('product-variants.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
        </form>
    </div></div></div></div>
@endsection
