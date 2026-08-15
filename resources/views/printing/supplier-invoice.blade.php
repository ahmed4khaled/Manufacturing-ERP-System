<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        {{ $document->invoice_number ?? 'Supplier Invoice' }}
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

        .invoice {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 2px solid #222;
        }

        .company-name {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .company-subtitle {
            margin-top: 4px;
            color: #666;
            font-size: 10px;
        }

        .invoice-title {
            text-align: left;
        }

        .invoice-title h1 {
            margin: 0;
            font-size: 19px;
        }

        .invoice-number {
            margin-top: 5px;
            font-weight: 700;
            font-size: 11px;
        }

        /* =========================
           INFORMATION
        ========================= */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 9px;
        }

        .box-title {
            color: #666;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .supplier-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
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
           PURCHASE ORDER
        ========================= */

        .section {
            margin-top: 14px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .po-header {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 9px;
        }

        .po-info {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
        }

        .po-info-label {
            color: #666;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .po-info-value {
            font-size: 11px;
            font-weight: 700;
        }

        /* =========================
           TABLES
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
           PO ITEMS
        ========================= */

        .po-items-table th,
        .po-items-table td {
            font-size: 10px;
        }

        .material-name {
            font-weight: 700;
        }

        .material-code {
            color: #777;
            font-size: 9px;
        }

        /* =========================
           GOODS RECEIPT
        ========================= */

        .reference-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .reference {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 9px;
        }

        .reference-label {
            color: #666;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .reference-value {
            font-size: 12px;
            font-weight: 700;
        }

        /* =========================
           INVOICE TOTALS
        ========================= */

        .financial-table {
            margin-top: 10px;
        }

        .financial-table th,
        .financial-table td {
            padding: 7px;
        }

        .grand-total {
            font-size: 13px;
            font-weight: 800;
            background: #f1f1f1;
        }

        /* =========================
           PAYMENT SUMMARY
        ========================= */

        .totals-wrapper {
            display: flex;
            justify-content: flex-start;
            margin-top: 12px;
        }

        .totals {
            width: 330px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }

        .total-row:last-child {
            border-bottom: 0;
        }

        .total-row.remaining {
            font-size: 13px;
            font-weight: 800;
            background: #f5f5f5;
        }

        /* =========================
           NOTES
        ========================= */

        .notes {
            margin-top: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 9px;
        }

        .notes-title {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .notes-text {
            color: #555;
            white-space: pre-line;
        }

        /* =========================
           PAYMENTS
        ========================= */

        .payments {
            margin-top: 15px;
        }

        .payments-table th,
        .payments-table td {
            font-size: 9px;
            padding: 6px;
        }

        /* =========================
           SIGNATURES
        ========================= */

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            margin-top: 40px;
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

            .invoice {
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


<div class="invoice">


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


        <div class="invoice-title">

            <h1>
                فاتورة مشتريات
            </h1>

            <div class="invoice-number">
                {{ $document->invoice_number ?? '—' }}
            </div>

        </div>

    </div>


    {{-- =========================
         SUPPLIER / INVOICE
    ========================= --}}

    <div class="info-grid">


        {{-- Supplier --}}

        <div class="box">

            <div class="box-title">
                بيانات المورد
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


        {{-- Invoice Information --}}

        <div class="box">

            <div class="box-title">
                بيانات الفاتورة
            </div>


            <div class="info-row">

                <span class="label">
                    رقم الفاتورة
                </span>

                <span class="value">
                    {{ $document->invoice_number ?? '—' }}
                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    تاريخ الفاتورة
                </span>

                <span class="value">
                    {{ $document->invoice_date?->format('Y-m-d') ?? '—' }}
                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    تاريخ الاستحقاق
                </span>

                <span class="value">
                    {{ $document->due_date?->format('Y-m-d') ?? '—' }}
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
                أمر الشراء
            </div>


            <div class="po-header">


                <div class="po-info">

                    <div class="po-info-label">
                        رقم أمر الشراء
                    </div>

                    <div class="po-info-value">

                        {{ $document->purchaseOrder->purchase_order_number }}

                    </div>

                </div>


                <div class="po-info">

                    <div class="po-info-label">
                        تاريخ أمر الشراء
                    </div>

                    <div class="po-info-value">

                        {{ $document->purchaseOrder->order_date?->format('Y-m-d') ?? '—' }}

                    </div>

                </div>


                <div class="po-info">

                    <div class="po-info-label">
                        المورد
                    </div>

                    <div class="po-info-value">

                        {{ $document->purchaseOrder->supplier?->name
                            ?? $document->supplier?->name
                            ?? '—' }}

                    </div>

                </div>


                <div class="po-info">

                    <div class="po-info-label">
                        حالة أمر الشراء
                    </div>

                    <div class="po-info-value">

                        {{ str_replace(
                            '_',
                            ' ',
                            ucfirst($document->purchaseOrder->status ?? '—')
                        ) }}

                    </div>

                </div>

            </div>


            {{-- Purchase Order Items --}}

            @if ($document->purchaseOrder->items?->count())

                <table class="po-items-table">

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

                                    <div class="material-name">

                                        {{ $item->material?->name
                                            ?? $item->material_name
                                            ?? '—' }}

                                    </div>

                                </td>


                                <td>

                                    <div class="material-code">

                                        {{ $item->material?->code
                                            ?? $item->material_code
                                            ?? '—' }}

                                    </div>

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
                                                ($item->quantity
                                                    ?? $item->ordered_quantity
                                                    ?? 0)
                                                *
                                                ($item->unit_cost
                                                    ?? $item->unit_price
                                                    ?? 0)
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

                <div>
                    غير مرتبط بأمر شراء
                </div>

            </div>

        </div>

    @endif


    {{-- =========================
         GOODS RECEIPT
    ========================= --}}

    <div class="section">

        <div class="section-title">
            بيانات الاستلام
        </div>


        <div class="reference-grid">


            <div class="reference">

                <div class="reference-label">
                    إذن الاستلام
                </div>

                <div class="reference-value">

                    {{ $document->goodsReceipt?->receipt_number
                        ?? 'غير مرتبط' }}

                </div>

            </div>


            <div class="reference">

                <div class="reference-label">
                    تاريخ الاستلام
                </div>

                <div class="reference-value">

                    {{ $document->goodsReceipt?->received_at?->format('Y-m-d')
                        ?? '—' }}

                </div>

            </div>

        </div>

    </div>


    {{-- =========================
         INVOICE FINANCIALS
    ========================= --}}

    <div class="section">

        <div class="section-title">
            تفاصيل الفاتورة
        </div>


        <table class="financial-table">

            <thead>

                <tr>

                    <th>
                        البيان
                    </th>

                    <th class="text-end">
                        القيمة
                    </th>

                </tr>

            </thead>


            <tbody>

                <tr>

                    <td>
                        المجموع الفرعي
                    </td>

                    <td class="text-end">

                        {{ number_format(
                            $document->subtotal ?? 0,
                            2
                        ) }}

                        جنيه

                    </td>

                </tr>


                <tr>

                    <td>
                        الخصم
                    </td>

                    <td class="text-end">

                        {{ number_format(
                            $document->discount ?? 0,
                            2
                        ) }}

                        جنيه

                    </td>

                </tr>


                <tr>

                    <td>
                        الضريبة
                    </td>

                    <td class="text-end">

                        {{ number_format(
                            $document->tax ?? 0,
                            2
                        ) }}

                        جنيه

                    </td>

                </tr>


                <tr class="grand-total">

                    <td>
                        إجمالي الفاتورة
                    </td>

                    <td class="text-end">

                        {{ number_format(
                            $document->total ?? 0,
                            2
                        ) }}

                        جنيه

                    </td>

                </tr>

            </tbody>

        </table>

    </div>


    {{-- =========================
         PAYMENT SUMMARY
    ========================= --}}

    <div class="totals-wrapper">

        <div class="totals">


            <div class="total-row">

                <span>
                    إجمالي الفاتورة
                </span>

                <strong>

                    {{ number_format(
                        $document->total ?? 0,
                        2
                    ) }}

                    جنيه

                </strong>

            </div>


            <div class="total-row">

                <span>
                    المدفوع
                </span>

                <strong>

                    {{ number_format(
                        $document->paid_amount ?? 0,
                        2
                    ) }}

                    جنيه

                </strong>

            </div>


            <div class="total-row remaining">

                <span>
                    المتبقي
                </span>

                <strong>

                    {{ number_format(
                        $document->remaining_amount ?? 0,
                        2
                    ) }}

                    جنيه

                </strong>

            </div>

        </div>

    </div>


    {{-- =========================
         PAYMENT HISTORY
    ========================= --}}

    @if ($document->payments && $document->payments->count())

        <div class="payments">

            <div class="section-title">
                سجل المدفوعات
            </div>


            <table class="payments-table">

                <thead>

                    <tr>

                        <th>
                            رقم الدفعة
                        </th>

                        <th>
                            التاريخ
                        </th>

                        <th>
                            طريقة الدفع
                        </th>

                        <th>
                            المرجع
                        </th>

                        <th class="text-end">
                            المبلغ
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach ($document->payments as $payment)

                        <tr>

                            <td>
                                {{ $payment->payment_number ?? '—' }}
                            </td>

                            <td>
                                {{ $payment->payment_date?->format('Y-m-d') ?? '—' }}
                            </td>

                            <td>

                                {{ ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $payment->payment_method ?? ''
                                    )
                                ) }}

                            </td>

                            <td>
                                {{ $payment->reference ?? '—' }}
                            </td>

                            <td class="text-end">

                                {{ number_format(
                                    $payment->amount ?? 0,
                                    2
                                ) }}

                                جنيه

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    @endif


    {{-- =========================
         NOTES
    ========================= --}}

    @if ($document->notes)

        <div class="notes">

            <div class="notes-title">
                ملاحظات
            </div>

            <div class="notes-text">
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
                توقيع المورد
            </div>

        </div>


        <div>

            <div class="signature-line"></div>

            <div class="signature-label">
                المسؤول
            </div>

        </div>

    </div>


    {{-- =========================
         FOOTER
    ========================= --}}

    <div class="footer">

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