@extends('statistics.layout')

@section('statistics-title', 'إحصائيات المرتجعات')
@section('statistics-description', 'تحليل قيمة وكمية مرتجعات المبيعات والمنتجات والعملاء الأكثر إرجاعًا.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['قيمة المرتجعات', $money($statistics['returns']['value'])], ['كمية المرتجعات', $number($statistics['returns']['quantity'])], ['عدد المرتجعات', number_format($statistics['returns']['count'])], ['معدل المرتجع للفترة', $percent($statistics['returns']['rate'])]] as [$label, $value])
        <div class="col-6 col-xl-3"><div class="card stats-kpi h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="kpi-value mt-2">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header bg-white"><strong>اتجاه المرتجعات</strong></div><div class="card-body"><div class="chart-box"><canvas id="returnsChart"></canvas></div></div></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white"><strong>أكثر المنتجات إرجاعًا</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المنتج</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['returns']['top_products'] as $row)<tr><td><a href="{{ route('products.show', $row->id) }}">{{ $row->name }}</a></td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد مرتجعات مؤكدة.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white"><strong>العملاء الأعلى مرتجعات</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>العميل</th><th>العدد</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['returns']['top_customers'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->count }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">لا توجد مرتجعات مؤكدة.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
