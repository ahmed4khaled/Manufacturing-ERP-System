@extends('layouts.dashboard')
@section('title', 'الأرصدة الافتتاحية والانتقال للتشغيل')
@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-1">Cutover & Opening Balances</h1>
        <p class="text-muted mb-0">الحالة المالية والمخزنية المعتمدة عند الانتقال، وليست إعادة إنشاء للحركات التاريخية.</p>
    </div>
</div>

@can('cutover.create')
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">إعداد Cutover جديد</h2>
    <form method="POST" action="{{ route('cutover.store') }}" class="row g-3">@csrf
        <div class="col-md-3"><label class="form-label">تاريخ الـCutover *</label><input type="date" name="cutover_date" value="{{ old('cutover_date') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">المرجع *</label><input name="reference" value="{{ old('reference') }}" class="form-control" placeholder="GO-LIVE-2026-10-01" required></div>
        <div class="col-md-4"><label class="form-label">الوصف</label><input name="description" value="{{ old('description') }}" class="form-control"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">إنشاء مسودة</button></div>
    </form>
    @if($errors->any())<div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
</div></div>
@endcan

<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>الدفعة</th><th>المرجع</th><th>التاريخ</th><th>الحالة</th><th>السطور</th><th></th></tr></thead>
    <tbody>@forelse($batches as $batch)<tr>
        <td>{{ $batch->batch_number }}</td><td>{{ $batch->reference }}</td><td>{{ $batch->cutover_date->format('Y-m-d') }}</td>
        <td><span class="badge bg-{{ $batch->status === 'posted' ? 'success' : ($batch->status === 'reversed' ? 'secondary' : 'warning') }}">{{ $batch->status }}</span></td>
        <td>{{ $batch->balances_count }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('cutover.show', $batch) }}">فتح</a></td>
    </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">لا توجد دفعات Cutover.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $batches->links() }}</div>
@endsection
