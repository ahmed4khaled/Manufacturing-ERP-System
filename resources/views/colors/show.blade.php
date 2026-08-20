@extends('layouts.dashboard')

@section('title', $color->name)

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            @if($color->hex_code)<span class="d-inline-block rounded border" style="width:32px;height:32px;background:{{ $color->hex_code }}"></span>@endif
            <div><h1 class="h3 mb-0">{{ $color->name }}</h1><p class="text-muted mb-0">الكود: <code>{{ $color->code }}</code> · الرمز السداسي: <code>{{ $color->hex_code ?? '—' }}</code></p></div>
            <span class="badge bg-{{ $color->status->badgeClass() }}">{{ $color->status->label() }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('colors.edit', $color) }}" class="btn btn-primary">تعديل</a>
            <form action="{{ route('colors.destroy', $color) }}" method="POST" onsubmit="return confirm('هل تريد الحذف؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف</button></form>
        </div>
    </div>
    <div class="card"><div class="card-body">
        <p class="mb-1"><strong>المتغيرات التي تستخدم هذا اللون:</strong> {{ $color->variants_count }}</p>
        <p class="mb-0 small text-muted">تاريخ الإنشاء {{ $color->created_at->format('Y-m-d') }}</p>
    </div></div>
    <div class="mt-3"><a href="{{ route('colors.index') }}" class="btn btn-outline-secondary">رجوع</a></div>
@endsection
