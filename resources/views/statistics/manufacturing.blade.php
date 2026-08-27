@extends('statistics.layout')

@section('statistics-title', 'إحصائيات التصنيع')
@section('statistics-description', 'متابعة أوامر الإنتاج والكميات المخططة والمنفذة وأداء عمليات التصنيع.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['أوامر الإنتاج', $statistics['production']['totals']['orders']], ['المخطط', $number($statistics['production']['totals']['planned'])], ['المنتج', $number($statistics['production']['totals']['produced'])], ['المتبقي', $number($statistics['production']['totals']['remaining'])], ['مكتمل', $statistics['production']['totals']['completed']], ['قيد التنفيذ', $statistics['production']['totals']['in_progress']], ['معلق', $statistics['production']['totals']['on_hold']], ['ملغي', $statistics['production']['totals']['cancelled']], ['نسبة الاكتمال', $percent($statistics['production']['totals']['completion_rate'])]] as [$label, $value])
        <div class="col-6 col-md-3 col-xl"><div class="card stats-kpi h-100"><div class="card-body text-center"><small class="text-muted">{{ $label }}</small><div class="kpi-value mt-2">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white"><strong>المخطط مقابل المنتج</strong></div><div class="card-body"><div class="chart-box"><canvas id="plannedChart"></canvas></div></div></div></div>
    <div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white"><strong>اتجاه الإنتاج التام</strong></div><div class="card-body"><div class="chart-box"><canvas id="productionChart"></canvas></div></div></div></div>
</div>
<div class="row g-3 mt-3">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white"><strong>الإنتاج حسب المنتج</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المنتج</th><th>المخطط</th><th>المنتج</th><th>نسبة التنفيذ</th></tr></thead><tbody>@forelse($statistics['production']['by_product'] as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $number($row->planned) }}</td><td>{{ $number($row->produced) }}</td><td>{{ $percent($row->completion_rate) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">لا توجد أوامر إنتاج.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white"><strong>الإنتاج حسب العملية</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>العملية</th><th>مسند</th><th>منتج</th><th>جيد</th><th>عيوب</th></tr></thead><tbody>@forelse($statistics['production']['by_operation'] as $row)<tr><td>{{ $row->operation_name }}</td><td>{{ $number($row->assigned) }}</td><td>{{ $number($row->produced) }}</td><td>{{ $number($row->good) }}</td><td>{{ $number($row->defective) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">لا توجد بيانات عمليات.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
<h2 class="h5 stats-heading"><i class="bi bi-diagram-3 me-2"></i>مساءلة عمليات التصنيع</h2>
<div class="row g-3 mb-3">
    @foreach([['أعلى عملية إنتاجًا', $statistics['operations']['top_production'], 'produced'], ['أعلى نسبة عيوب', $statistics['operations']['highest_defect_rate'], 'defect_rate'], ['أعلى نسبة هالك', $statistics['operations']['highest_waste_rate'], 'waste_rate'], ['أفضل اكتمال', $statistics['operations']['best_completion'], 'completion_rate']] as [$label, $row, $field])
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body rank-summary"><small class="text-muted">{{ $label }}</small><div class="fw-bold mt-2">{{ $row?->name ?? '—' }}</div><div>{{ $row ? (str_contains($field, 'rate') ? $percent($row->{$field}) : $number($row->{$field})) : '—' }}</div>@if($row)<small class="text-muted">عينة الإنتاج: {{ $number($row->produced) }}</small>@endif</div></div></div>
    @endforeach
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 stats-table"><thead><tr><th>العملية</th><th>مسند</th><th>منتج</th><th>جيد</th><th>عيوب</th><th>نسبة العيوب</th><th>هالك</th><th>نسبة الهالك</th><th>إعادة تشغيل</th><th>اكتمال</th></tr></thead><tbody>@forelse($statistics['operations']['rows'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $number($row->assigned) }}</td><td>{{ $number($row->produced) }}</td><td>{{ $number($row->good) }}</td><td>{{ $number($row->defects) }}</td><td>{{ $percent($row->defect_rate) }}</td><td>{{ $number($row->waste) }}</td><td>{{ $percent($row->waste_rate) }}</td><td>{{ $number($row->rework) }}</td><td>{{ $percent($row->completion_rate) }}</td></tr>@empty<tr><td colspan="10" class="text-center text-muted">لا توجد بيانات عمليات.</td></tr>@endforelse</tbody></table></div></div>
@endsection
