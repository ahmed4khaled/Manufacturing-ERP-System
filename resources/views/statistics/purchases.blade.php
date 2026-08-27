@extends('statistics.layout')

@section('statistics-title', 'إحصائيات المشتريات')
@section('statistics-description', 'تحليل أوامر الشراء وفواتير الموردين والمدفوعات والمرتجعات والخامات المستلمة.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['إجمالي المشتريات', $money($statistics['purchasing']['total'])], ['أوامر الشراء', number_format($statistics['purchasing']['purchase_order_count'])], ['فواتير الموردين', number_format($statistics['purchasing']['supplier_invoice_count'])], ['مدفوعات الموردين', $money($statistics['purchasing']['payments'])], ['مرتجعات الشراء', $money($statistics['purchasing']['returns'])], ['رصيد الموردين الحالي', $money($statistics['purchasing']['payables'])]] as [$label, $value])
        <div class="col-6 col-md-4 col-xl-2"><div class="card stats-kpi h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="kpi-value mt-2">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header bg-white"><strong>اتجاه فواتير الموردين</strong></div><div class="card-body"><div class="chart-box"><canvas id="purchasingChart"></canvas></div></div></div>
<div class="row g-3">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white"><strong>أكبر الموردين بالقيمة</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المورد</th><th>المعاملات</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['purchasing']['top_suppliers'] as $row)<tr><td><a href="{{ route('suppliers.show', $row->id) }}">{{ $row->name }}</a></td><td>{{ $row->transactions }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد مشتريات.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white"><strong>أكثر الخامات شراءً</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الخامة</th><th>الكمية</th><th>متوسط السعر</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['purchasing']['materials'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $number($row->quantity) }}</td><td>{{ $money($row->average_cost) }}</td><td>{{ $money($row->value) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">لا توجد استلامات مؤكدة.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
<div class="row g-3 mt-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white"><strong>الأكثر تعاملًا</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المورد</th><th>المعاملات</th></tr></thead><tbody>@forelse($statistics['purchasing']['top_suppliers_transactions'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->transactions }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white"><strong>أعلى متوسط سعر خامة</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الخامة</th><th>متوسط السعر</th></tr></thead><tbody>@forelse($statistics['purchasing']['expensive_materials'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $money($row->average_cost) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white"><strong>أعلى أرصدة الموردين</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المورد</th><th>الرصيد</th></tr></thead><tbody>@forelse($statistics['purchasing']['supplier_balances'] as $row)<tr><td>{{ $row->name }}</td><td class="text-danger">{{ $money($row->outstanding) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد أرصدة.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
