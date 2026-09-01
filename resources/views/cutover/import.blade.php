@extends('layouts.dashboard')
@section('title','استيراد أرصدة Cutover')
@section('content')
<div class="page-header d-flex justify-content-between"><div><h1 class="h3">استيراد CSV — {{ $batch->batch_number }}</h1><p class="text-muted">المعاينة لا تحفظ أي بيانات. العملاء والموردون غير موجودين في القوالب عمدًا.</p></div><a href="{{ route('cutover.show',$batch) }}" class="btn btn-outline-secondary">رجوع</a></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body">
    <form method="POST" enctype="multipart/form-data" action="{{ route('cutover.import.preview',$batch) }}" class="row g-3">@csrf
        <div class="col-md-4"><label class="form-label">نوع القالب</label><select name="type" class="form-select" required>@foreach($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">ملف CSV</label><input type="file" name="file" accept=".csv,text/csv" class="form-control" required></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">معاينة</button></div>
    </form>
    <div class="mt-3 d-flex flex-wrap gap-2">@foreach($types as $type)<a class="btn btn-sm btn-outline-secondary" href="{{ route('cutover.templates',$type) }}">قالب {{ $type }}</a>@endforeach</div>
</div></div>
@if($preview)
<div class="row g-3 mb-4"><div class="col"><div class="card"><div class="card-body"><strong>Rows</strong><div class="fs-4">{{ $preview['total'] }}</div></div></div></div><div class="col"><div class="card border-success"><div class="card-body"><strong>Valid</strong><div class="fs-4 text-success">{{ $preview['valid'] }}</div></div></div></div><div class="col"><div class="card border-danger"><div class="card-body"><strong>Errors</strong><div class="fs-4 text-danger">{{ count($preview['errors']) }}</div></div></div></div><div class="col"><div class="card border-warning"><div class="card-body"><strong>Warnings</strong><div class="fs-4 text-warning">{{ count($preview['warnings']) }}</div></div></div></div></div>
@if($preview['errors'])<div class="alert alert-danger"><strong>الأخطاء</strong><ul class="mb-0">@foreach($preview['errors'] as $error)<li>السطر {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul></div>@endif
@if($preview['warnings'])<div class="alert alert-warning"><strong>التحذيرات</strong><ul class="mb-0">@foreach($preview['warnings'] as $warning)<li>السطر {{ $warning['row'] }}: {{ $warning['message'] }}</li>@endforeach</ul></div>@endif
@if(!$preview['errors'] && $preview['valid'] > 0)<form method="POST" action="{{ route('cutover.import.commit',$batch) }}">@csrf<button class="btn btn-success">حفظ السطور الصحيحة في المسودة</button></form>@endif
@endif
@endsection
