@extends('printing.layout')

@php
    $printTitle = 'إيصال استلام';
    $printNumber = $document->receipt_number;
    $printDate = $document->payment_date->format('Y-m-d');
    $paymentMethodLabel = $document->payment_method instanceof \App\Enums\PaymentMethod
        ? $document->payment_method->label()
        : \App\Support\ArabicLabels::paymentMethod((string) $document->payment_method);
    $allocatedTotal = $allocationBreakdown->sum(fn ($row) => (float) $row['allocation_amount']);
@endphp

@section('print-content')
<div class="info-grid">
    <div class="info-box">
        <div class="info-label">استلمنا من العميل</div>
        <div class="info-name">{{ $document->customer?->name ?? '—' }}</div>
        @if ($document->customer?->code)<div class="info-line">كود العميل: {{ $document->customer->code }}</div>@endif
        @if ($document->customer?->phone)<div class="info-line">الهاتف: {{ $document->customer->phone }}</div>@endif
    </div>
    <div class="info-box">
        <div class="info-label">تفاصيل التحصيل</div>
        <div class="info-line">رقم السند: <strong>{{ $document->receipt_number }}</strong></div>
        <div class="info-line">التاريخ: {{ $document->payment_date->format('Y-m-d') }}</div>
        <div class="info-line">طريقة الدفع: <strong>{{ $paymentMethodLabel }}</strong></div>
        <div class="info-line">الحالة: {{ $document->status->label() }}</div>
    </div>
</div>

<div style="margin:18px 0;padding:18px;border:2px solid #222;border-radius:5px;text-align:center">
    <div style="color:#777;font-size:10px;margin-bottom:6px">المبلغ المستلم</div>
    <div style="font-size:26px;font-weight:800">{{ number_format($document->amount, 2) }} جنيه</div>
    <div style="margin-top:8px">استلمنا من العميل {{ number_format($document->amount, 2) }} جنيه</div>
</div>

@if ($allocationBreakdown->isNotEmpty())
    <div style="margin:18px 0;border:1px solid #ddd;border-radius:5px;overflow:hidden">
        <div style="padding:9px 11px;background:#f1f1f1;border-bottom:1px solid #ddd;font-weight:700">تفاصيل سداد الفواتير</div>
        <div style="padding:8px 11px">تم توزيع المبلغ على الفواتير التالية:</div>
        <table class="items-table">
            <thead><tr><th>رقم الفاتورة</th><th>إجمالي الفاتورة</th><th>المدفوع سابقًا</th><th>هذه الدفعة</th><th>المتبقي</th></tr></thead>
            <tbody>@foreach ($allocationBreakdown as $row)<tr>
                <td>{{ $row['invoice']->invoice_number }}</td>
                <td>{{ number_format((float) $row['invoice_total'], 2) }}</td>
                <td>{{ number_format((float) $row['previous_paid'], 2) }}</td>
                <td><strong>{{ number_format((float) $row['allocation_amount'], 2) }}</strong></td>
                <td>{{ number_format((float) $row['remaining'], 2) }}</td>
            </tr>@endforeach</tbody>
        </table>
    </div>
@else
    <div class="notes"><div class="notes-title">نوع الدفعة</div><div class="notes-content">دفعة مقدمة غير موزعة على فواتير.</div></div>
@endif

<table class="items-table">
    <tbody>
        <tr><td>إجمالي السند</td><td>{{ number_format($document->amount, 2) }} جنيه</td></tr>
        <tr><td>إجمالي التوزيع</td><td>{{ number_format($allocatedTotal, 2) }} جنيه</td></tr>
        <tr><td>غير موزع</td><td>{{ number_format(max(0, (float) $document->amount - $allocatedTotal), 2) }} جنيه</td></tr>
        @if ($customerBalance !== null)<tr><td>رصيد العميل المستحق</td><td>{{ number_format($customerBalance, 2) }} جنيه</td></tr>@endif
        @if ($document->reference_number)<tr><td>رقم المرجع</td><td>{{ $document->reference_number }}</td></tr>@endif
    </tbody>
</table>

@if ($document->notes)<div class="notes"><div class="notes-title">ملاحظات</div><div class="notes-content">{{ $document->notes }}</div></div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:40px;padding-top:12px;border-top:1px solid #eee">
    <div style="text-align:center"><div style="margin-bottom:28px;font-weight:700">توقيع العميل / المستلم</div><div style="width:75%;height:1px;margin:auto;background:#888"></div></div>
    <div style="text-align:center"><div style="margin-bottom:28px;font-weight:700">مسؤول KONOZ</div><div style="width:75%;height:1px;margin:auto;background:#888"></div></div>
</div>
@endsection
