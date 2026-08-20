@extends('printing.layout')

@php
    $printTitle = 'مرتجع مبيعات';
    $printNumber = $document->return_number;
    $printDate = $document->return_date->format('Y-m-d');
@endphp

@section('print-content')
    <div class="info-grid">
        <div class="info-box"><div class="info-label">بيانات العميل</div><div class="info-name">{{ $document->customer->name }}</div><div class="info-line">كود العميل: {{ $document->customer->code }}</div></div>
        <div class="info-box"><div class="info-label">المرجع</div><div class="info-line">فاتورة المبيعات: <strong>{{ $document->salesInvoice->invoice_number }}</strong></div><div class="info-line">الحالة: {{ $document->status->label() }}</div></div>
    </div>

    <table class="items-table">
        <thead><tr><th>#</th><th>الصنف</th><th>اللون</th><th>المقاس</th><th>الرمز</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
        <tbody>@foreach($document->items as $index => $item)<tr><td>{{ $index + 1 }}</td><td>{{ $item->product_name }}</td><td>{{ $item->color_name ?? '—' }}</td><td>{{ $item->size_name ?? '—' }}</td><td>{{ $item->sku }}</td><td>{{ number_format($item->quantity, 3) }}</td><td>{{ number_format($item->unit_price, 2) }}</td><td>{{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody>
    </table>

    <div class="summary-wrapper"><div class="summary"><div class="summary-row"><span>إجمالي الكمية</span><strong>{{ number_format($document->total_quantity, 3) }}</strong></div><div class="summary-row total"><span>قيمة المرتجع</span><span>{{ number_format($document->total_amount, 2) }} جنيه</span></div></div></div>
    <div class="notes"><div class="notes-title">سبب المرتجع</div><div class="notes-content">{{ $document->reason }}</div>@if($document->notes)<div class="notes-content">{{ $document->notes }}</div>@endif</div>
@endsection
