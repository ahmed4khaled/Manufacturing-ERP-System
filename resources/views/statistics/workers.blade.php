@extends('statistics.layout')

@section('statistics-title', 'إحصائيات العمال')
@section('statistics-description', 'قياس إنتاجية العمال بالقيم المطلقة ومعدلات العيوب والهالك والاكتمال.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['أعلى إنتاجية', $statistics['workers']['top_production'], 'produced'], ['أعلى نسبة عيوب', $statistics['workers']['highest_defect_rate'], 'defect_rate'], ['أعلى نسبة هالك', $statistics['workers']['highest_waste_rate'], 'waste_rate'], ['أعلى إعادة تشغيل', $statistics['workers']['highest_rework'], 'rework'], ['أفضل اكتمال', $statistics['workers']['best_completion'], 'completion_rate']] as [$label, $row, $field])
        <div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body rank-summary"><small class="text-muted">{{ $label }}</small><div class="fw-bold mt-2">{{ $row?->name ?? '—' }}</div><div>{{ $row ? (str_contains($field, 'rate') ? $percent($row->{$field}) : $number($row->{$field})) : '—' }}</div>@if($row)<small class="text-muted">عينة الإنتاج: {{ $number($row->produced) }}</small>@endif</div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white"><strong>الإنتاج والعيوب حسب العامل</strong></div><div class="card-body"><div class="chart-box"><canvas id="workersChart"></canvas></div></div></div></div>
    <div class="col-xl-7"><div class="card h-100"><div class="table-responsive"><table class="table table-hover mb-0 stats-table"><thead><tr><th>#</th><th>العامل</th><th>مسند</th><th>منتج</th><th>جيد</th><th>عيوب</th><th>نسبة العيوب</th><th>هالك</th><th>نسبة الهالك</th><th>إعادة</th><th>اكتمال</th></tr></thead><tbody>@forelse($statistics['workers']['rows'] as $row)<tr><td>{{ $loop->iteration }}</td><td><a href="{{ route('workers.show', $row->id) }}">{{ $row->name }}</a></td><td>{{ $number($row->assigned) }}</td><td>{{ $number($row->produced) }}</td><td>{{ $number($row->good) }}</td><td>{{ $number($row->defects) }}</td><td>{{ $percent($row->defect_rate) }}</td><td>{{ $number($row->waste) }}</td><td>{{ $percent($row->waste_rate) }}</td><td>{{ $number($row->rework) }}</td><td>{{ $percent($row->completion_rate) }}</td></tr>@empty<tr><td colspan="11" class="text-center text-muted">لا توجد تكليفات في الفترة.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
