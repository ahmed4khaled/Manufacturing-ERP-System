@extends('printing.layout')

@php

    $printTitle = 'فاتورة مبيعات';

    $printNumber = $document->invoice_number;

    $printDate = $document->invoice_date->format('Y-m-d');

@endphp


@section('print-content')

<style>
    /* =========================
   Signatures
========================== */

.invoice-signatures {
    display: grid;
    grid-template-columns: 1fr 1fr;

    gap: 80px;

    margin-top: 35px;
    padding-top: 12px;

    border-top: 1px solid #eee;
}

.signature-box {
    text-align: center;

    padding: 8px 20px;
}

.signature-title {
    margin-bottom: 28px;

    color: #444;

    font-size: 10px;
    font-weight: 700;
}

.signature-line {
    width: 75%;

    height: 1px;

    margin: 0 auto;

    background: #888;
}

.signature-hint {
    margin-top: 6px;

    color: #999;

    font-size: 8px;
}
</style>
    {{-- =========================
         Customer / Invoice Info
    ========================== --}}

    <div class="info-grid">

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
                {{ number_format($document->total_quantity, 0) }}
            </div>

        </div>

    </div>


    {{-- =========================
         Items
    ========================== --}}

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
                        {{ number_format($item->unit_price, 2) }}
                    </td>

                    <td>
                        {{ number_format($item->quantity, 0) }}
                    </td>

                    <td>
                        {{ number_format($item->line_total, 2) }}
                    </td>

                </tr>

            @endforeach

        </tbody>

    </table>


    {{-- =========================
         Summary
    ========================== --}}

    <div class="summary-wrapper">

        <div class="summary">

            <div class="summary-row">

                <span>
                    إجمالي القطع
                </span>

                <strong>
                    {{ number_format($document->total_quantity, 0) }}
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    المجموع الفرعي
                </span>

                <strong>
                    {{ number_format($document->subtotal, 2) }}
                    جنيه
                </strong>

            </div>


            <div class="summary-row total">

                <span>
                    الإجمالي
                </span>

                <span>
                    {{ number_format($document->total_amount, 2) }}
                    جنيه
                </span>

            </div>

        </div>

    </div>


    {{-- =========================
         Notes
    ========================== --}}

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


    {{-- =========================
         Signatures
    ========================== --}}

    <div class="invoice-signatures">

        <div class="signature-box">

            <div class="signature-title">
                توقيع العميل / المستلم
            </div>

            <div class="signature-line"></div>

            <div class="signature-hint">
                الاسم والتوقيع
            </div>

        </div>


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


@endsection