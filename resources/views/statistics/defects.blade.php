@extends('statistics.layout')

@section('statistics-title', 'إحصائيات العيوب والهالك')
@section('statistics-description', 'تحليل أسباب العيوب وإعادة التشغيل والهالك وانحراف الخامات حسب المنتج والعامل والعملية.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['عدد بلاغات العيوب', $statistics['defects']['count']], ['كمية العيوب', $number($statistics['defects']['quantity'])], ['نسبة العيوب', $percent($statistics['defects']['rate'])], ['إعادة التشغيل', $number($statistics['defects']['rework_quantity'])], ['نجاح إعادة التشغيل', $number($statistics['defects']['rework_good'])], ['معدل اكتمال إعادة التشغيل', $percent($statistics['defects']['rework_completion_rate'])], ['هالك نهائي', $number($statistics['defects']['scrap'])], ['هالك الخامات', $number($statistics['materials']['totals']['waste'])], ['نسبة هالك الخامات', $percent($statistics['materials']['totals']['waste_rate'])], ['انحراف الخامات', $number($statistics['materials']['totals']['variance'])]] as [$label, $value])
        <div class="col-6 col-md-4 col-xl"><div class="card stats-kpi h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="kpi-value mt-2">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white"><strong>أسباب العيوب</strong></div><div class="card-body"><div class="chart-box"><canvas id="defectsChart"></canvas></div></div></div></div>
    <div class="col-xl-7"><div class="card h-100"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>السبب</th><th>البلاغات</th><th>الكمية</th></tr></thead><tbody>@forelse($statistics['defects']['by_reason'] as $row)<tr><td>{{ $row->label }}</td><td>{{ $row->count }}</td><td>{{ $number($row->quantity) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد عيوب.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
<div class="row g-3 mt-3">
    @foreach([['العيوب حسب المنتج', $statistics['defects']['by_product'], 'product_name'], ['العيوب حسب العامل', $statistics['defects']['by_worker'], 'name'], ['العيوب حسب العملية', $statistics['defects']['by_operation'], 'operation_name']] as [$title, $rows, $field])
        <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white"><strong>{{ $title }}</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>البيان</th><th>الكمية</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->{$field} }}</td><td>{{ $number($row->quantity) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div>
    @endforeach
</div>
<div class="row g-3 mt-3"><div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white"><strong>استهلاك وهالك الخامات</strong></div><div class="card-body"><div class="chart-box"><canvas id="materialsChart"></canvas></div></div></div></div><div class="col-xl-7"><div class="card h-100"><div class="table-responsive"><table class="table mb-0 stats-table"><thead><tr><th>الخامة</th><th>مستخدم</th><th>هالك</th><th>نسبة الهالك</th><th>الانحراف</th></tr></thead><tbody>@forelse($statistics['materials']['rows'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $number($row->used) }}</td><td>{{ $number($row->waste) }}</td><td>{{ $percent($row->waste_rate) }}</td><td>{{ $number($row->variance) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">لا توجد حركات خامات إنتاج.</td></tr>@endforelse</tbody></table></div></div></div></div>
@endsection
