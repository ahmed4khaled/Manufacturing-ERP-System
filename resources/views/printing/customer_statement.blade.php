@extends('printing.layout')

@php

    $printTitle = 'كشف حساب عميل';

    $printNumber = $document->code;

    $printDate = now()->format('Y-m-d');

@endphp


@section('print-content')

    {{-- =========================================================
         Customer Information
    ========================================================== --}}

    <div class="info-grid">

        <div class="info-box">

            <div class="info-label">
                بيانات العميل
            </div>

            <div class="info-name">
                {{ $document->name ?? '—' }}
            </div>

            @if ($document->code)

                <div class="info-line">
                    كود العميل:
                    {{ $document->code }}
                </div>

            @endif

            @if ($document->phone)

                <div class="info-line">
                    الهاتف:
                    {{ $document->phone }}
                </div>

            @endif

        </div>


        <div class="info-box">

            <div class="info-label">
                ملخص الحساب
            </div>

            <div class="info-line">
                إجمالي المبيعات:
                <strong>
                    {{ number_format($totalDebit, 2) }}
                    جنيه
                </strong>
            </div>

            <div class="info-line">
                إجمالي المدفوعات:
                <strong>
                    {{ number_format($totalCredit, 2) }}
                    جنيه
                </strong>
            </div>

            <div class="info-line">
                الرصيد المستحق:
                <strong>
                    {{ number_format($currentBalance, 2) }}
                    جنيه
                </strong>
            </div>

        </div>

    </div>



    {{-- =========================================================
         Account Statement
    ========================================================== --}}

    <div
        style="
            margin-top: 15px;
            margin-bottom: 15px;
        "
    >

        <div
            style="
                margin-bottom: 7px;
                font-size: 12px;
                font-weight: 700;
            "
        >
            حركة حساب العميل
        </div>


        <table class="items-table">

            <thead>

                <tr>

                    <th>
                        التاريخ
                    </th>

                    <th>
                        البيان
                    </th>

                    <th>
                        رقم المستند
                    </th>

                    <th>
                        مدين
                    </th>

                    <th>
                        دائن
                    </th>

                    <th>
                        الرصيد
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse ($entries as $entry)

                    <tr>

                        <td>
                            {{ \Carbon\Carbon::parse($entry->transaction_date)->format('Y-m-d') }}
                        </td>


                        <td>

                             @if (in_array($entry->description, ['Sales Invoice', 'فاتورة مبيعات'], true))

                                {{ ('فاتورة مبيعات رقم')  }}

                            @elseif(in_array($entry->description, ['Customer Payment Receipt', 'سند قبض عميل'], true))

                                {{ ('سند قبض عميل رقم')  }}


                            @endif

                        </td>


                        <td>

                            @if ($entry->reference_number)

                                <strong>
                                    {{ $entry->reference_number }}
                                </strong>

                            @else

                                —

                            @endif

                        </td>


                        <td>

                            @if ((float) $entry->debit > 0)

                                {{ number_format((float) $entry->debit, 2) }}

                            @else

                                —

                            @endif

                        </td>


                        <td>

                            @if ((float) $entry->credit > 0)

                                {{ number_format((float) $entry->credit, 2) }}

                            @else

                                —

                            @endif

                        </td>


                        <td>

                            <strong>
                                {{ number_format((float) $entry->running_balance, 2) }}
                            </strong>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            style="
                                text-align: center;
                                padding: 20px;
                                color: #777;
                            "
                        >
                            لا توجد حركات مسجلة على حساب العميل.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>



    {{-- =========================================================
         Final Balance Box
    ========================================================== --}}

    <div
        style="
            margin-top: 18px;
            padding: 14px 16px;
            border: 2px solid #222;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        "
    >

        <div>

            <div
                style="
                    color: #777;
                    font-size: 9px;
                    margin-bottom: 4px;
                "
            >
                الرصيد المستحق على العميل
            </div>

            <div
                style="
                    font-size: 17px;
                    font-weight: 800;
                "
            >
                {{ number_format($currentBalance, 2) }}
                جنيه
            </div>

        </div>


        <div
            style="
                text-align: left;
                color: #777;
                font-size: 9px;
            "
        >

            حتى تاريخ

            <br>

            {{ now()->format('Y-m-d') }}

        </div>

    </div>



    {{-- =========================================================
         Statement Note
    ========================================================== --}}

    <div
        style="
            margin-top: 15px;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #666;
            font-size: 9px;
            line-height: 1.7;
        "
    >

        هذا الكشف يوضح الفواتير والمدفوعات والحركات المسجلة
        على حساب العميل حتى تاريخ إصدار الكشف.

    </div>



    {{-- =========================================================
         Signatures
    ========================================================== --}}

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
