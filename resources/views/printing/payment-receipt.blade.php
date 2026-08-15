@extends('printing.layout')

@php

    $printTitle = 'إيصال استلام';

    $printNumber = $document->receipt_number;

    $printDate = $document->payment_date->format('Y-m-d');

    $paymentMethod = $document->payment_method instanceof \BackedEnum
        ? $document->payment_method->value
        : $document->payment_method;

    $paymentMethodLabels = [
        'cash' => 'نقدي',
        'bank_transfer' => 'تحويل بنكي',
        'cheque' => 'شيك',
        'other' => 'أخرى',
    ];

    $paymentMethodLabel =
        $paymentMethodLabels[$paymentMethod]
        ?? ucfirst(str_replace('_', ' ', $paymentMethod));


    /*
    |--------------------------------------------------------------------------
    | Invoice Payment Information
    |--------------------------------------------------------------------------
    */

$invoice = $document->salesInvoice ?? $document->invoice ?? null;

$invoiceTotal = (float) ($invoice?->total_amount ?? 0);

$currentPayment = (float) $document->amount;

$previousPaid = (float) ($previousPaid ?? 0);

$invoiceRemaining = $invoice
    ? max(
        0,
        $invoiceTotal
        - $previousPaid
        - $currentPayment
    )
    : null;

$customerBalance = isset($customerBalance)
    ? (float) $customerBalance
    : null;

@endphp


@section('print-content')


{{-- =========================================================
     Customer Information
========================================================= --}}

<div class="info-grid">

    <div class="info-box">

        <div class="info-label">
            استلمنا من العميل
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
            تفاصيل التحصيل
        </div>

        @if ($invoice)

            <div class="info-line">
                فاتورة المبيعات:
                <strong>
                    {{ $invoice->invoice_number }}
                </strong>
            </div>

        @endif

        <div class="info-line">
            طريقة الدفع:
            <strong>
                {{ $paymentMethodLabel }}
            </strong>
        </div>

        @if ($document->reference_number)

            <div class="info-line">
                رقم المرجع:
                {{ $document->reference_number }}
            </div>

        @endif

        <div class="info-line">
            الحالة:
            {{ $document->status->label() }}
        </div>

    </div>

</div>



{{-- =========================================================
     Payment Amount
========================================================= --}}

<div
    style="
        margin: 18px 0;
        padding: 18px;
        border: 2px solid #222;
        border-radius: 5px;
        text-align: center;
    "
>

    <div
        style="
            color: #777;
            font-size: 10px;
            margin-bottom: 6px;
        "
    >
        المبلغ المستلم
    </div>

    <div
        style="
            font-size: 26px;
            font-weight: 800;
        "
    >
        {{ number_format($currentPayment, 2) }}
        جنيه
    </div>

</div>



{{-- =========================================================
     Invoice Payment Summary
========================================================= --}}

@if ($invoice)

    <div
        style="
            margin-top: 18px;
            margin-bottom: 18px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        "
    >

        <div
            style="
                padding: 9px 11px;
                background: #f1f1f1;
                border-bottom: 1px solid #ddd;
                font-size: 11px;
                font-weight: 700;
            "
        >
            تفاصيل سداد الفاتورة
        </div>


        <table>

            <tbody>

                <tr>

                    <td style="padding: 7px 10px;">
                        رقم الفاتورة
                    </td>

                    <td
                        style="
                            padding: 7px 10px;
                            text-align: left;
                            font-weight: 700;
                        "
                    >
                        {{ $invoice->invoice_number }}
                    </td>

                </tr>


                <tr>

                    <td style="padding: 7px 10px;">
                        إجمالي الفاتورة
                    </td>

                    <td
                        style="
                            padding: 7px 10px;
                            text-align: left;
                        "
                    >
                        {{ number_format($invoiceTotal, 2) }}
                        جنيه
                    </td>

                </tr>


                <tr>

                    <td style="padding: 7px 10px;">
                        المدفوع سابقًا
                    </td>

                    <td
                        style="
                            padding: 7px 10px;
                            text-align: left;
                        "
                    >
                        {{ number_format($previousPaid, 2) }}
                        جنيه
                    </td>

                </tr>


                <tr>

                    <td
                        style="
                            padding: 8px 10px;
                            font-weight: 700;
                        "
                    >
                        هذه الدفعة
                    </td>

                    <td
                        style="
                            padding: 8px 10px;
                            text-align: left;
                            font-weight: 700;
                        "
                    >
                        {{ number_format($currentPayment, 2) }}
                        جنيه
                    </td>

                </tr>


                <tr>

                    <td
                        style="
                            padding: 9px 10px;
                            border-top: 2px solid #222;
                            font-weight: 700;
                        "
                    >
                        المتبقي على الفاتورة
                    </td>

                    <td
                        style="
                            padding: 9px 10px;
                            text-align: left;
                            border-top: 2px solid #222;
                            font-size: 13px;
                            font-weight: 800;
                        "
                    >
                        {{ number_format($invoiceRemaining, 2) }}
                        جنيه
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

@endif



{{-- =========================================================
     Customer Balance
========================================================= --}}

@if ($customerBalance !== null)

    <div
        style="
            margin-top: 15px;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        "
    >

        <span
            style="
                font-size: 10px;
                color: #666;
                font-weight: 700;
            "
        >
            إجمالي رصيد العميل المستحق
        </span>

        <strong
            style="
                font-size: 15px;
            "
        >
            {{ number_format($customerBalance, 2) }}
            جنيه
        </strong>

    </div>

@endif



{{-- =========================================================
     Payment Details
========================================================= --}}

<table class="items-table">

    <thead>

        <tr>

            <th>
                البيان
            </th>

            <th>
                التفاصيل
            </th>

        </tr>

    </thead>


    <tbody>

        <tr>

            <td>
                نوع العملية
            </td>

            <td>
                تحصيل دفعة من العميل
            </td>

        </tr>


        @if ($invoice)

            <tr>

                <td>
                    الفاتورة المسددة
                </td>

                <td>
                    {{ $invoice->invoice_number }}
                </td>

            </tr>

        @endif


        <tr>

            <td>
                طريقة الدفع
            </td>

            <td>
                {{ $paymentMethodLabel }}
            </td>

        </tr>


        @if ($document->reference_number)

            <tr>

                <td>
                    رقم المرجع
                </td>

                <td>
                    {{ $document->reference_number }}
                </td>

            </tr>

        @endif

    </tbody>

</table>



{{-- =========================================================
     Notes
========================================================= --}}

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
========================================================= --}}

<div
    style="
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        margin-top: 40px;
        padding-top: 12px;
        border-top: 1px solid #eee;
    "
>

    <div
        style="
            text-align: center;
            padding: 8px 20px;
        "
    >

        <div
            style="
                margin-bottom: 28px;
                color: #444;
                font-size: 10px;
                font-weight: 700;
            "
        >
            توقيع العميل / المستلم
        </div>

        <div
            style="
                width: 75%;
                height: 1px;
                margin: 0 auto;
                background: #888;
            "
        ></div>

        <div
            style="
                margin-top: 6px;
                color: #999;
                font-size: 8px;
            "
        >
            الاسم والتوقيع
        </div>

    </div>


    <div
        style="
            text-align: center;
            padding: 8px 20px;
        "
    >

        <div
            style="
                margin-bottom: 28px;
                color: #444;
                font-size: 10px;
                font-weight: 700;
            "
        >
            مسؤول KONOZ
        </div>

        <div
            style="
                width: 75%;
                height: 1px;
                margin: 0 auto;
                background: #888;
            "
        ></div>

        <div
            style="
                margin-top: 6px;
                color: #999;
                font-size: 8px;
            "
        >
            الاسم والتوقيع
        </div>

    </div>

</div>


@endsection