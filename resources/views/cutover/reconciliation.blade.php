@extends('layouts.dashboard')
@section('title','مطابقة Cutover')
@section('content')
<div class="page-header d-flex justify-content-between"><div><h1 class="h3">مطابقة {{ $batch->batch_number }}</h1><p class="text-muted">مقارنة تفاصيل الافتتاح بالقيد المرتبط بالدفعة فقط.</p></div><a href="{{ route('cutover.show',$batch) }}" class="btn btn-outline-secondary">رجوع</a></div>
<div class="alert {{ $report['ready'] ? 'alert-success' : 'alert-danger' }}"><strong>{{ $report['ready'] ? 'READY FOR GO-LIVE' : 'NOT READY' }}</strong></div>
<div class="card mb-4"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>القسم</th><th>Opening Detail</th><th>GL</th><th>Difference</th><th>الحالة</th></tr></thead><tbody>
@foreach($report['sections'] as $section)<tr><td>{{ $section['label'] }}</td><td>{{ number_format((float)$section['expected'],2) }}</td><td>{{ number_format((float)$section['actual'],2) }}</td><td>{{ number_format((float)$section['difference'],2) }}</td><td><span class="badge bg-{{ $section['matched']?'success':'danger' }}">{{ $section['matched']?'Matched':'Mismatch' }}</span></td></tr>@endforeach
</tbody></table></div></div>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><strong>Opening Clearing</strong><div class="fs-4">{{ number_format((float)$report['clearing'],2) }}</div><span class="badge bg-{{ bccomp($report['clearing'],'0',2)===0?'success':'danger' }}">{{ bccomp($report['clearing'],'0',2)===0?'Zero':'Needs review' }}</span></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><strong>طبقات غير مُقيّمة</strong><div class="fs-4">{{ $report['unvalued'] }}</div><span class="badge bg-{{ $report['unvalued']===0?'success':'warning' }}">{{ $report['unvalued']===0?'None':'Not ready' }}</span></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><strong>الفترات الشهرية</strong><div class="fs-4">{{ $report['presentPeriods'] }}/{{ $report['expectedPeriods'] }}</div><span class="badge bg-{{ $report['monthlyPeriodsReady']?'success':'danger' }}">{{ $report['monthlyPeriodsReady']?'Ready':'Missing' }}</span></div></div></div>
</div>
<div class="alert alert-info mt-4">Customers: Already Migrated — Excluded. Suppliers: Already Migrated — Excluded. لا يقرأ هذا التقرير دفاترهم ولا يغيرها.</div>
@endsection
