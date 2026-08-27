@extends('statistics.layout')

@section('statistics-title', 'إحصائيات المبيعات')
@section('statistics-description', 'تحليل الفواتير والمبيعات حسب المنتج والمتغير واللون والمقاس.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['إجمالي المبيعات', $money($statistics['sales']['total']), 'bi-receipt'], ['الكمية المباعة', $number($statistics['sales']['quantity']), 'bi-box'], ['عدد الفواتير', number_format($statistics['sales']['count']), 'bi-file-earmark-text'], ['متوسط الفاتورة', $money($statistics['sales']['average']), 'bi-calculator']] as [$label, $value, $icon])
        <div class="col-6 col-xl-3"><div class="card stats-kpi h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><div><small class="text-muted">{{ $label }}</small><div class="kpi-value mt-2">{{ $value }}</div></div><span class="kpi-icon"><i class="bi {{ $icon }}"></i></span></div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header bg-white"><strong>اتجاه المبيعات</strong></div><div class="card-body"><div class="chart-box"><canvas id="salesChart"></canvas></div></div></div>

<div class="card"><div class="card-header bg-white"><ul class="nav nav-tabs card-header-tabs"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sales-products">حسب المنتج</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sales-variants">حسب المتغير</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sales-colors">الألوان والمقاسات</button></li></ul></div><div class="tab-content">
    <div class="tab-pane show active" id="sales-products"><div class="table-responsive"><table class="table table-hover mb-0 stats-table"><thead><tr><th>#</th><th>المنتج</th><th>الكود</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['sales']['by_product'] as $row)<tr><td>{{ $loop->iteration }}</td><td><a href="{{ route('products.show', $row->id) }}">{{ $row->name }}</a></td><td>{{ $row->code }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">لا توجد مبيعات مؤكدة في الفترة.</td></tr>@endforelse</tbody></table></div></div>
    <div class="tab-pane" id="sales-variants"><div class="table-responsive"><table class="table mb-0 stats-table"><thead><tr><th>المنتج</th><th>اللون</th><th>المقاس</th><th>SKU</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['sales']['by_variant'] as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->color_name ?: '—' }}</td><td>{{ $row->size_name ?: '—' }}</td><td>{{ $row->sku }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div>
    <div class="tab-pane" id="sales-colors"><div class="row g-0"><div class="col-md-6"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>اللون</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['sales']['by_color'] as $row)<tr><td>{{ $row->label }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div><div class="col-md-6 border-start"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المقاس</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['sales']['by_size'] as $row)<tr><td>{{ $row->label }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div></div>
</div></div>

<div class="row g-3 mt-3">
    @foreach([['الأكثر مبيعًا بالكمية', $statistics['sales']['top_products_quantity']], ['الأكثر مبيعًا بالقيمة', $statistics['sales']['top_products_value']], ['الأقل مبيعًا خلال الفترة', $statistics['sales']['lowest_products']]] as [$title, $rows])
        <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white"><strong>{{ $title }}</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المنتج</th><th>الكمية</th><th>القيمة</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->name }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div>
    @endforeach
</div>
@endsection
