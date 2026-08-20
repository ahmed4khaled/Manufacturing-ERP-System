@extends('printing.layout')

@php

    $printTitle = 'فاتورة مبيعات';

    $printNumber = $document->invoice_number;

    $printDate = $document->invoice_date->format('Y-m-d');

@endphp


@section('print-content')

<style>

/* =========================================================
   Invoice Payment Status
========================================================= */

.invoice-payment-box {
    margin-top: 10px;
    padding: 8px 10px;

    border: 1px solid #ddd;
    border-radius: 4px;

    background: #fafafa;
}

.invoice-payment-title {
    margin-bottom: 6px;

    color: #777;

    font-size: 9px;
    font-weight: 700;
}

.invoice-payment-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);

    gap: 8px;
}

.invoice-payment-item {
    text-align: center;

    padding: 5px;

    border-left: 1px solid #e5e5e5;
}

.invoice-payment-item:last-child {
    border-left: 0;
}

.invoice-payment-label {
    color: #777;
    font-size: 8px;
}

.invoice-payment-value {
    margin-top: 2px;

    font-size: 10px;
    font-weight: 700;
}


/* =========================================================
   Signatures
========================================================= */

.invoice-signatures {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 55px;

    margin-top: 32px;

    padding-top: 12px;

    border-top: 1px solid #ddd;

}

.signature-box {

    text-align: center;

    padding: 5px 15px;

}

.signature-title {

    margin-bottom: 25px;

    color: #444;

    font-size: 10px;

    font-weight: 700;

}

.signature-line {

    width: 70%;

    height: 1px;

    margin: 0 auto;

    background: #777;

}

.signature-hint {

    margin-top: 5px;

    color: #999;

    font-size: 8px;

}


/* =========================================================
   Footer Note
========================================================= */

.invoice-thanks {

    margin-top: 18px;

    padding-top: 8px;

    border-top: 1px dashed #ddd;

    text-align: center;

    color: #777;

    font-size: 9px;

    line-height: 1.7;

}

.invoice-thanks strong {

    color: #444;

    letter-spacing: 1px;

}


/* =========================================================
   Print
========================================================= */

@media print {

    .invoice-signatures {

        page-break-inside: avoid;

    }

    .invoice-payment-box {

        page-break-inside: avoid;

    }

}

</style>


{{-- =========================================================
     Customer / Invoice Info
========================================================== --}}

<div class="info-grid">


    {{-- Customer --}}

    <div class="info-box">

        <div class="info-label">
            بيانات العميل
        </div>


        <div class="info-name">

            {{ $document->customer?->name ?? '—' }}

        </div>


        @if ($document->customer?->code)

            <div class="info-line">

                كود العميل:

                {{ $document->customer->code }}

            </div>

        @endif


        @if ($document->customer?->phone)

            <div class="info-line">

                الهاتف:

                {{ $document->customer->phone }}

            </div>

        @endif

    </div>


    {{-- Invoice --}}

    <div class="info-box">

        <div class="info-label">
            بيانات الفاتورة
        </div>


        <div class="info-line">

            الحالة:

            {{ $document->status->label() }}

        </div>


        <div class="info-line">

            عدد القطع:

            {{ number_format($document->total_quantity, 3) }}

        </div>

    </div>

</div>



{{-- =========================================================
     Payment Status
========================================================== --}}

@if (
    $document->status === \App\Enums\SalesInvoiceStatus::CONFIRMED
)

    <div class="invoice-payment-box">

        <div class="invoice-payment-title">
            حالة السداد
        </div>


        <div class="invoice-payment-grid">


            {{-- Payment Status --}}

            <div class="invoice-payment-item">

                <div class="invoice-payment-label">
                    حالة الدفع
                </div>

                <div class="invoice-payment-value">

                    {{ $document->payment_status_label }}

                </div>

            </div>


            {{-- Returns --}}

            <div class="invoice-payment-item">

                <div class="invoice-payment-label">
                    المرتجعات
                </div>

                <div class="invoice-payment-value">

                    {{ number_format(
                        $document->returned_amount,
                        2
                    ) }}

                    جنيه

                </div>

            </div>


            {{-- Paid --}}

            <div class="invoice-payment-item">

                <div class="invoice-payment-label">
                    المدفوع
                </div>

                <div class="invoice-payment-value">

                    {{ number_format(
                        $document->paid_amount,
                        2
                    ) }}

                    جنيه

                </div>

            </div>


            {{-- Remaining --}}

            <div class="invoice-payment-item">

                <div class="invoice-payment-label">
                    المتبقي
                </div>

                <div class="invoice-payment-value">

                    {{ number_format(
                        $document->remaining_amount,
                        2
                    ) }}

                    جنيه

                </div>

            </div>


        </div>

    </div>

@endif



{{-- =========================================================
     Items
========================================================== --}}

<table class="items-table">

    <thead>

        <tr>

            <th>
                #
            </th>

            <th>
                الصنف
            </th>

            <th>
                اللون
            </th>

            <th>
                المقاس
            </th>

            <th>
                الكود
            </th>

            <th>
                السعر
            </th>

            <th>
                الكمية
            </th>

            <th>
                الإجمالي
            </th>

        </tr>

    </thead>


    <tbody>

        @foreach ($document->items as $index => $item)

            <tr>

                <td>
                    {{ $index + 1 }}
                </td>


                <td>
                    {{ $item->product_name }}
                </td>


                <td>
                    {{ $item->color_name ?? '—' }}
                </td>


                <td>
                    {{ $item->size_name ?? '—' }}
                </td>


                <td>
                    {{ $item->sku ?? '—' }}
                </td>


                <td>

                    {{ number_format(
                        $item->unit_price,
                        2
                    ) }}

                </td>


                <td>

                    {{ number_format(
                        $item->quantity,
                        3
                    ) }}

                </td>


                <td>

                    {{ number_format(
                        $item->line_total,
                        2
                    ) }}

                </td>

            </tr>

        @endforeach

    </tbody>

</table>



{{-- =========================================================
     Summary
========================================================== --}}

<div class="summary-wrapper">

    <div class="summary">


        <div class="summary-row">

            <span>
                إجمالي القطع
            </span>

            <strong>

                {{ number_format(
                    $document->total_quantity,
                    3
                ) }}

            </strong>

        </div>


        <div class="summary-row">

            <span>
                المجموع الفرعي
            </span>

            <strong>

                {{ number_format(
                    $document->subtotal,
                    2
                ) }}

                جنيه

            </strong>

        </div>


        <div class="summary-row total">

            <span>
                الإجمالي
            </span>

            <span>

                {{ number_format(
                    $document->total_amount,
                    2
                ) }}

                جنيه

            </span>

        </div>


    </div>

</div>



{{-- =========================================================
     Notes
========================================================== --}}

@if ($document->notes)

    <div class="notes">

        <div class="notes-title">
            ملاحظات
        </div>


        <div class="notes-content">

            {{ $document->notes }}

        </div>

    </div>

@endif



{{-- =========================================================
     Signatures
========================================================== --}}

<div class="invoice-signatures">


    {{-- Customer --}}

    <div class="signature-box">

        <div class="signature-title">

            توقيع العميل / المستلم

        </div>


        <div class="signature-line"></div>


        <div class="signature-hint">

            الاسم والتوقيع

        </div>

    </div>


    {{-- KONOZ --}}

    <div class="signature-box">

        <div class="signature-title">

            مسؤول KONOZ

        </div>


        <div class="signature-line"></div>


        <div class="signature-hint">

            الاسم والتوقيع

        </div>

    </div>


</div>



{{-- =========================================================
     Thanks
========================================================== --}}

<div class="invoice-thanks">

    شكرًا لتعاملكم معنا، ونسعد دائمًا بخدمتكم.

    <br>

    <strong>
        KONOZ
    </strong>

</div>


@endsection
