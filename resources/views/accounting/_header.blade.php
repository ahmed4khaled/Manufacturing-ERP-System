<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><h1 class="h3 mb-1">{{ $title }}</h1><p class="text-muted mb-0">المصدر: القيود اليومية المرحّلة فقط</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.accounts') }}">دليل الحسابات</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.journals') }}">القيود</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.ledger') }}">الأستاذ</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.trial-balance') }}">ميزان المراجعة</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
