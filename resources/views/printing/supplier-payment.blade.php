<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        سند صرف {{ $document->payment_number ?? '' }}
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #222;
            font-family: Arial, Tahoma, sans-serif;
            font-size: 11px;
            direction: rtl;
        }

        .document {
            width: 100%;
            max-width: 800px;
            margin: auto;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #222;
            padding-bottom: 14px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 27px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .company-subtitle {
            color: #666;
            font-size: 10px;
            margin-top: 4px;
        }

        .document-title {
            text-align: left;
        }

        .document-title h1 {
            margin: 0;
            font-size: 20px;
        }

        .document-number {
            margin-top: 6px;
            font-weight: 700;
            font-size: 11px;
        }

        /* =========================
           BOXES
        ========================= */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }

        .box-title {
            color: #666;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .supplier-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 5px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .label {
            color: #666;
        }

        .value {
            font-weight: 600;
        }

        /* =========================
           SECTION
        ========================= */

        .section {
            margin-top: 15px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        /* =========================
           PURCHASE ORDER
        ========================= */

        .po-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 9px;
        }

        .po-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
        }

        .po-label {
            color: #666;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .po-value {
            font-weight: 700;
            font-size: 11px;
        }

        /* =========================
           TABLE
        ========================= */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f1f1f1;
            font-weight: 700;
        }

        th,
        td {
            border: 1px solid #d8d8d8;
            padding: 7px;
        }

        .text-end {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        /* =========================
           PAYMENT
        ========================= */

        .payment-type {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 9px 10px;
            margin-top: 12px;
        }

        .payment-type-label {
            color: #666;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .payment-type-value {
            font-weight: 700;
            font-size: 12px;
        }

        .amount-box {
            border: 2px solid #222;
            border-radius: 6px;
            text-align: center;
            padding: 16px;
            margin: 16px 0;
        }

        .amount-label {
            color: #666;
            margin-bottom: 6px;
        }

        .amount {
            font-size: 25px;
            font-weight: 800;
        }

        /* =========================
           NOTES
        ========================= */

        .notes {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 9px;
            margin-top: 13px;
        }

        .notes-title {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .notes-content {
            color: #555;
            white-space: pre-line;
        }

        /* =========================
           SIGNATURES
        ========================= */

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            margin-top: 45px;
            text-align: center;
        }

        .signature-line {
            height: 28px;
            border-bottom: 1px solid #888;
        }

        .signature-label {
            margin-top: 6px;
            color: #666;
            font-size: 9px;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            margin-top: 22px;
            padding-top: 9px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 9px;
            line-height: 1.8;
        }

        /* =========================
           PRINT
        ========================= */

        .print-button {
            position: fixed;
            top: 15px;
            left: 15px;
            padding: 8px 15px;
            border: 0;
            border-radius: 5px;
            background: #222;
            color: #fff;
            cursor: pointer;
        }

        @media print {

            body {
                padding: 0;
            }

            .document {
                max-width: none;
            }

            .print-button {
                display: none;
            }

            @page {
                size: A4;
                margin: 10mm;
            }

        }

    </style>

</head>


<body>

<button
    class="print-button"
    onclick="window.print()"
>
    طباعة
</button>


<div class="document">


    {{-- =========================
         HEADER
    ========================= --}}

    <div class="header">

        <div>

            <div class="company-name">
                KONOZ
            </div>

            <div class="company-subtitle">
                نظام إدارة المصنع
            </div>

        </div>


        <div class="document-title">

            <h1>
                سند صرف مورد
            </h1>

            <div class="document-number">

                رقم السند:
                {{ $document->payment_number ?? '—' }}

            </div>

        </div>

    </div>


    {{-- =========================
         SUPPLIER + PAYMENT INFO
    ========================= --}}

    <div class="info-grid">


        {{-- المورد --}}

        <div class="box">

            <div class="box-title">
                المورد المستفيد
            </div>

            <div class="supplier-name">

                {{ $document->supplier?->name ?? '—' }}

            </div>


            @if ($document->supplier?->code)

                <div class="info-row">

                    <span class="label">
                        كود المورد
                    </span>

                    <span class="value">
                        {{ $document->supplier->code }}
                    </span>

                </div>

            @endif


            @if ($document->supplier?->phone)

                <div class="info-row">

                    <span class="label">
                        الهاتف
                    </span>

                    <span class="value">
                        {{ $document->supplier->phone }}
                    </span>

                </div>

            @endif

        </div>


        {{-- Payment Info --}}

        <div class="box">

            <div class="box-title">
                بيانات الصرف
            </div>


            <div class="info-row">

                <span class="label">
                    تاريخ الدفع
                </span>

                <span class="value">

                    {{ $document->payment_date?->format('Y-m-d') ?? '—' }}

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    طريقة الدفع
                </span>

                <span class="value">

                    {{ \App\Support\ArabicLabels::paymentMethod($document->payment_method) }}

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    المرجع
                </span>

                <span class="value">

                    {{ $document->reference ?? '—' }}

                </span>

            </div>

        </div>

    </div>


    {{-- =========================
         PURCHASE ORDER
    ========================= --}}

    @if ($document->purchaseOrder)

        <div class="section">

            <div class="section-title">
                أمر الشراء المرتبط
            </div>


            <div class="po-grid">


                <div class="po-box">

                    <div class="po-label">
                        رقم أمر الشراء
                    </div>

                    <div class="po-value">

                        {{ $document->purchaseOrder->purchase_order_number }}

                    </div>

                </div>


                <div class="po-box">

                    <div class="po-label">
                        تاريخ أمر الشراء
                    </div>

                    <div class="po-value">

                        {{ $document->purchaseOrder->order_date?->format('Y-m-d')
                            ?? '—' }}

                    </div>

                </div>


                <div class="po-box">

                    <div class="po-label">
                        المورد
                    </div>

                    <div class="po-value">

                        {{ $document->purchaseOrder->supplier?->name
                            ?? $document->supplier?->name
                            ?? '—' }}

                    </div>

                </div>


                <div class="po-box">

                    <div class="po-label">
                        الحالة
                    </div>

                    <div class="po-value">

                        {{ \App\Support\ArabicLabels::status($document->purchaseOrder->status) }}

                    </div>

                </div>

            </div>


            {{-- Purchase Order Items --}}

            @if ($document->purchaseOrder->items?->count())

                <table>

                    <thead>

                        <tr>

                            <th>
                                المادة
                            </th>

                            <th>
                                الكود
                            </th>

                            <th class="text-center">
                                الكمية
                            </th>

                            <th class="text-end">
                                سعر الوحدة
                            </th>

                            <th class="text-end">
                                الإجمالي
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @foreach ($document->purchaseOrder->items as $item)

                            <tr>

                                <td>

                                    {{ $item->material?->name
                                        ?? $item->material_name
                                        ?? '—' }}

                                </td>


                                <td>

                                    {{ $item->material?->code
                                        ?? $item->material_code
                                        ?? '—' }}

                                </td>


                                <td class="text-center">

                                    {{ number_format(
                                        $item->quantity
                                            ?? $item->ordered_quantity
                                            ?? 0,
                                        3
                                    ) }}

                                </td>


                                <td class="text-end">

                                    {{ number_format(
                                        $item->unit_cost
                                            ?? $item->unit_price
                                            ?? 0,
                                        2
                                    ) }}

                                    جنيه

                                </td>


                                <td class="text-end">

                                    {{ number_format(
                                        $item->line_total
                                            ?? (
                                                (
                                                    $item->quantity
                                                    ?? $item->ordered_quantity
                                                    ?? 0
                                                )
                                                *
                                                (
                                                    $item->unit_cost
                                                    ?? $item->unit_price
                                                    ?? 0
                                                )
                                            ),
                                        2
                                    ) }}

                                    جنيه

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            @else

                <div class="box">

                    لا توجد بنود مسجلة في أمر الشراء.

                </div>

            @endif

        </div>

    @else

        <div class="section">

            <div class="box">

                <div class="box-title">
                    أمر الشراء
                </div>

                لا يوجد أمر شراء مرتبط بهذا السند.

            </div>

        </div>

    @endif


    {{-- =========================
         SUPPLIER INVOICE
    ========================= --}}

    @if ($document->invoice)

        <div class="section">

            <div class="section-title">
                فاتورة المورد المرتبطة
            </div>


            <div class="info-grid">

                <div class="box">

                    <div class="info-row">

                        <span class="label">
                            رقم الفاتورة
                        </span>

                        <span class="value">

                            {{ $document->invoice->invoice_number }}

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="label">
                            تاريخ الفاتورة
                        </span>

                        <span class="value">

                            {{ $document->invoice->invoice_date?->format('Y-m-d')
                                ?? '—' }}

                        </span>

                    </div>

                </div>


                <div class="box">

                    <div class="info-row">

                        <span class="label">
                            إجمالي الفاتورة
                        </span>

                        <span class="value">

                            {{ number_format(
                                (float) $document->invoice->total,
                                2
                            ) }}

                            جنيه

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="label">
                            المتبقي بعد الفاتورة
                        </span>

                        <span class="value">

                            {{ number_format(
                                (float) $document->invoice->remaining_amount,
                                2
                            ) }}

                            جنيه

                        </span>

                    </div>

                </div>

            </div>

        </div>

    @endif


    {{-- =========================
         PAYMENT TYPE
    ========================= --}}

    <div class="payment-type">

        <div class="payment-type-label">
            نوع الدفعة
        </div>

        <div class="payment-type-value">

            @if ($document->payment_type === 'advance')

                دفعة مقدمة

            @elseif ($document->payment_type === 'invoice')

                سداد فاتورة مورد

            @else

                {{ \App\Support\ArabicLabels::paymentType($document->payment_type) }}

            @endif

        </div>

    </div>


    {{-- =========================
         AMOUNT
    ========================= --}}

    <div class="amount-box">

        <div class="amount-label">
            المبلغ المصروف للمورد
        </div>

        <div class="amount">

            {{ number_format(
                (float) $document->amount,
                2
            ) }}

            جنيه

        </div>

    </div>


    {{-- =========================
         NOTES
    ========================= --}}

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
         SIGNATURES
    ========================= --}}

    <div class="signatures">

        <div>

            <div class="signature-line"></div>

            <div class="signature-label">
                توقيع مستلم المبلغ
            </div>

        </div>


        <div>

            <div class="signature-line"></div>

            <div class="signature-label">
                المسؤول عن الصرف
            </div>

        </div>

    </div>


    {{-- =========================
         FOOTER
    ========================= --}}

    <div class="footer">

        تم صرف المبلغ الموضح أعلاه إلى المورد.

        <br>

        شكرًا لتعاملكم مع KONOZ

    </div>


</div>


<script>

    window.addEventListener('load', function () {

        setTimeout(function () {
            window.print();
        }, 500);

    });

</script>

</body>

</html>
