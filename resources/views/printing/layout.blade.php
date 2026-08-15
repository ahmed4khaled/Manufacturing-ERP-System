<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $printTitle ?? 'KONOZ' }}
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #eee;
            color: #222;

            font-family:
                Tahoma,
                Arial,
                "Segoe UI",
                sans-serif;

            font-size: 11px;
            direction: rtl;
        }

        .print-actions {
            width: 190mm;
            margin: 15px auto;
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            direction: ltr;
        }

        .print-actions button,
        .print-actions a {
            padding: 7px 14px;
            border-radius: 4px;
            border: 1px solid #aaa;
            background: #fff;
            color: #222;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
        }

        .print-actions .primary {
            background: #222;
            color: #fff;
            border-color: #222;
        }

        .print-document {
            width: 190mm;
            min-height: 270mm;

            margin: 0 auto;

            padding: 10mm;

            background: #fff;
        }

        /* =========================
           Header
        ========================== */

        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;

            padding-bottom: 12px;
            margin-bottom: 14px;

            border-bottom: 2px solid #222;
        }

        .brand-name {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .brand-subtitle {
            margin-top: 4px;
            color: #777;
            font-size: 9px;
        }

        .document-title {
            text-align: left;
        }

        .document-title h1 {
            margin: 0;
            font-size: 21px;
        }

        .document-number {
            margin-top: 4px;
            font-weight: 700;
            font-size: 12px;
        }

        .document-date {
            margin-top: 3px;
            color: #777;
            font-size: 10px;
        }

        /* =========================
           Info Boxes
        ========================== */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }

        .info-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 9px 11px;
        }

        .info-label {
            margin-bottom: 4px;
            color: #777;
            font-size: 9px;
            font-weight: 700;
        }

        .info-name {
            font-size: 13px;
            font-weight: 700;
        }

        .info-line {
            margin-top: 3px;
            color: #666;
            font-size: 10px;
        }

        /* =========================
           Tables
        ========================== */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table {
            margin-top: 5px;
        }

        .items-table th {
            padding: 7px 6px;

            background: #f1f1f1;

            border-top: 1px solid #888;
            border-bottom: 1px solid #888;

            font-size: 9px;
            font-weight: 700;
        }

        .items-table td {
            padding: 7px 6px;

            border-bottom: 1px solid #e5e5e5;

            font-size: 10px;
        }

        /* =========================
           Summary
        ========================== */

        .summary-wrapper {
            display: flex;
            justify-content: flex-start;

            margin-top: 15px;
        }

        .summary {
            width: 250px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;

            padding: 5px 0;

            border-bottom: 1px solid #e5e5e5;

            font-size: 10px;
        }

        .summary-row.total {
            margin-top: 4px;

            padding: 8px 0;

            border-top: 2px solid #222;
            border-bottom: 0;

            font-size: 14px;
            font-weight: 700;
        }

        /* =========================
           Notes
        ========================== */

        .notes {
            margin-top: 15px;

            padding: 8px 10px;

            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .notes-title {
            margin-bottom: 4px;

            color: #777;

            font-size: 9px;
            font-weight: 700;
        }

        .notes-content {
            font-size: 10px;
        }

        /* =========================
           Footer
        ========================== */

        .document-footer {
            margin-top: 30px;

            padding-top: 10px;

            border-top: 1px solid #ddd;

            text-align: center;

            color: #777;

            font-size: 10px;

            line-height: 1.8;
        }

        .document-footer strong {
            color: #555;
            letter-spacing: 1px;
        }

        /* =========================
           Print
        ========================== */

        @media print {

            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #fff;
            }

            .print-actions {
                display: none !important;
            }

            .print-document {
                width: 190mm;
                min-height: 270mm;

                margin: 0 auto;

                padding: 10mm 0;
            }
        }

        @media screen and (max-width: 900px) {

            body {
                padding: 10px;
            }

            .print-actions {
                width: 100%;
            }

            .print-document {
                width: 100%;
                min-height: auto;
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
.document-footer {
    margin-top: 24px;
    padding-top: 10px;

    border-top: 1px solid #ddd;

    display: flex;
    justify-content: space-between;
    align-items: flex-end;

    gap: 20px;

    color: #777;
    font-size: 9px;
    line-height: 1.6;
}

.footer-main {
    flex: 1;
    text-align: center;
    padding-bottom: 4px;
}

.thank-you {
    font-size: 10px;
    margin-bottom: 5px;
}

.brand-footer {
    color: #555;
    font-weight: 700;
    letter-spacing: 1.5px;
}


/* System Box */

.system-promotion {
    width: 230px;

    padding: 9px 11px;

    border: 1px solid #aaa;
    border-radius: 5px;

    background: #fafafa;

    text-align: right;
}

.promotion-title {
    color: #222;
    font-size: 9px;
    font-weight: 700;

    margin-bottom: 3px;
}

.promotion-text {
    color: #777;
    font-size: 8px;

    margin-bottom: 5px;
}

.promotion-contact {
    color: #555;
    font-size: 8px;
}

.promotion-contact strong {
    color: #222;
}
    </style>

</head>

<body>

    <div class="print-actions">

        <a href="javascript:history.back()">
            رجوع
        </a>

        <button
            type="button"
            class="primary"
            onclick="window.print()"
        >
            طباعة
        </button>

    </div>

    <main class="print-document">

        <header class="document-header">

            <div>

                <div class="brand-name">
                    KONOZ
                </div>

                <div class="brand-subtitle">
                    مصنع كونوذ للعبايات
                </div>

            </div>

            <div class="document-title">

                <h1>
                    {{ $printTitle ?? 'مستند' }}
                </h1>

                @if (!empty($printNumber))

                    <div class="document-number">
                        رقم:
                        {{ $printNumber }}
                    </div>

                @endif

                @if (!empty($printDate))

                    <div class="document-date">
                        التاريخ:
                        {{ $printDate }}
                    </div>

                @endif

            </div>

        </header>


        {{-- محتوى المستند --}}

        @yield('print-content')


     <footer class="document-footer">

    <div class="footer-main">

        <div class="thank-you">
            شكرًا لتعاملكم معنا، ونسعد دائمًا بخدمتكم.
        </div>

        <div class="brand-footer">
            KONOZ
        </div>

    </div>


    <div class="system-promotion">

        <div class="promotion-title">
            هل تحتاج إلى نظام لإدارة الفواتير والمخزون والمبيعات؟
        </div>

        <div class="promotion-text">
            نوفر لك نظام إدارة متكامل مصمم حسب احتياجات نشاطك.
        </div>

        <div class="promotion-contact">
            تواصل معنا
            <br>
            <strong>01126201412</strong>
            &nbsp; | &nbsp;
            <strong>WhatsApp / Phone
            </strong>
        </div>

    </div>

</footer>

    </main>

</body>

</html>