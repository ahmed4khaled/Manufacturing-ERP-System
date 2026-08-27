@extends('statistics.layout')

@section('statistics-title', 'لوحة الإحصائيات')
@section('statistics-description', 'نظرة تنفيذية مختصرة على أهم مؤشرات المصنع خلال الفترة المحددة.')

@section('statistics-content')
@php
    $kpis = [
        ['إجمالي المبيعات', $money($statistics['kpis']['sales']), 'bi-receipt'],
        ['إجمالي التحصيلات', $money($statistics['kpis']['collections']), 'bi-cash-coin'],
        ['رصيد العملاء الحالي', $money($statistics['kpis']['customer_receivables']), 'bi-people'],
        ['إجمالي المشتريات', $money($statistics['kpis']['purchases']), 'bi-cart-check'],
        ['رصيد الموردين الحالي', $money($statistics['kpis']['supplier_payables']), 'bi-truck'],
        ['مخزون المنتجات', $number($statistics['kpis']['product_inventory']), 'bi-boxes'],
        ['الخامات المتاحة', number_format($statistics['kpis']['material_inventory']).' خامة', 'bi-box-seam'],
        ['أوامر الإنتاج المفتوحة', number_format($statistics['kpis']['open_production_orders']), 'bi-gear-wide-connected'],
        ['نسبة اكتمال الإنتاج', $percent($statistics['kpis']['production_completion_rate']), 'bi-speedometer2'],
    ];
@endphp

<div class="row g-3">
    @foreach($kpis as [$label, $value, $icon])
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card stats-kpi h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><div><small class="text-muted d-block mb-2">{{ $label }}</small><div class="kpi-value">{{ $value }}</div></div><span class="kpi-icon"><i class="bi {{ $icon }}"></i></span></div></div></div>
        </div>
    @endforeach
</div>

<h2 class="h5 stats-heading"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>يحتاج الانتباه</h2>
<div class="row g-3">
    @foreach($statistics['alerts'] as $alert)
        <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between mb-3"><div><i class="bi bi-{{ $alert['icon'] }} text-{{ $alert['type'] }} me-2"></i><strong>{{ $alert['label'] }}</strong></div><span class="badge bg-{{ $alert['type'] }}">{{ $alert['count'] }}</span></div>
            @forelse($alert['items'] as $item)<div class="d-flex justify-content-between small border-top py-2"><span>{{ $item->name ?? $item->product_name ?? $item->order_number ?? 'سجل' }}</span><strong>{{ isset($item->outstanding) ? $money($item->outstanding) : $number($item->remaining ?? $item->variance ?? $item->stock ?? 0) }}</strong></div>@empty<span class="text-muted small">لا توجد حالات.</span>@endforelse
        </div></div></div>
    @endforeach
</div>

<h2 class="h5 stats-heading"><i class="bi bi-graph-up me-2"></i>الاتجاهات الرئيسية</h2>
<div class="row g-3">
    @foreach([['salesChart', 'اتجاه المبيعات'], ['collectionsChart', 'اتجاه التحصيلات'], ['productionChart', 'اتجاه الإنتاج التام'], ['purchasingChart', 'اتجاه المشتريات']] as [$id, $title])
        <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white"><strong>{{ $title }}</strong></div><div class="card-body"><div class="chart-box"><canvas id="{{ $id }}"></canvas></div></div></div></div>
    @endforeach
</div>
@endsection
