@extends('statistics.layout')

@section('statistics-title', 'إحصائيات التحصيلات')
@section('statistics-description', 'تحليل سندات القبض وطرق الدفع واتجاه التحصيل وأعلى العملاء سدادًا.')

@section('statistics-content')
<div class="row g-3 mb-3">
    <div class="col-xl-4"><div class="card stats-kpi h-100"><div class="card-body"><small class="text-muted">إجمالي التحصيلات</small><div class="kpi-value mt-2">{{ $money($statistics['collections']['total']) }}</div></div></div></div>
    @foreach($statistics['collections']['by_method'] as $row)
        <div class="col-6 col-xl-2"><div class="card stats-kpi h-100"><div class="card-body"><small class="text-muted">{{ $row->label }}</small><div class="kpi-value mt-2">{{ $money($row->value) }}</div><small class="text-muted">{{ $row->count }} سند</small></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header bg-white"><strong>اتجاه التحصيلات</strong></div><div class="card-body"><div class="chart-box"><canvas id="collectionsChart"></canvas></div></div></div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white"><strong>أعلى العملاء سدادًا</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>العميل</th><th>المدفوع</th></tr></thead><tbody>@forelse($statistics['collections']['top_customers'] as $row)<tr><td>{{ $row->name }}</td><td>{{ $money($row->payments) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد تحصيلات.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white"><strong>أكبر سندات القبض</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>السند</th><th>العميل</th><th>التاريخ</th><th>القيمة</th></tr></thead><tbody>@forelse($statistics['collections']['largest'] as $row)<tr><td><a href="{{ route('payment-receipts.show', $row->id) }}">{{ $row->receipt_number }}</a></td><td>{{ $row->customer_name }}</td><td>{{ $row->payment_date }}</td><td>{{ $money($row->amount) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">لا توجد تحصيلات.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
