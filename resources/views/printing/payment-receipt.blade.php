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
    ];

    $paymentMethodLabel =
        $paymentMethodLabels[$paymentMethod]
        ?? ucfirst(str_replace('_', ' ', $paymentMethod));

@endphp


@section('print-content')

    {{-- Customer Information --}}
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


    {{-- Payment Amount --}}
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
            {{ number_format($document->amount, 2) }}
            جنيه
        </div>

    </div>


    {{-- Payment Details --}}
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


    {{-- Notes --}}
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


    {{-- Signature --}}
    <div
        style="
            display: flex;
            justify-content: space-between;
            margin-top: 45px;
            text-align: center;
        "
    >

        <div style="width: 180px;">

            <div
                style="
                    border-bottom: 1px solid #999;
                    height: 30px;
                "
            ></div>

            <div
                style="
                    margin-top: 6px;
                    color: #777;
                    font-size: 9px;
                "
            >
                توقيع العميل
            </div>

        </div>


        <div style="width: 180px;">

            <div
                style="
                    border-bottom: 1px solid #999;
                    height: 30px;
                "
            ></div>

            <div
                style="
                    margin-top: 6px;
                    color: #777;
                    font-size: 9px;
                "
            >
                الموظف المسؤول
            </div>

        </div>

    </div>

@endsection