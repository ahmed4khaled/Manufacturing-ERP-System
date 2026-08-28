<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: 50mm 32mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { background: #ecebea; color: #101010; font-family: "Helvetica Neue", Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 58px; padding: 10px 16px; background: rgba(255,255,255,.96); border-bottom: 1px solid #d8d5d1; }
        .toolbar-count { margin-inline-end: 8px; color: #625f5a; font-size: 10pt; }
        .toolbar button { border: 1px solid #171717; border-radius: 2px; padding: 8px 20px; background: #171717; color: #fff; font: 600 9pt Arial, sans-serif; cursor: pointer; }
        .toolbar .secondary { background: #fff; color: #171717; }
        .labels { display: grid; grid-template-columns: repeat(auto-fill, 50mm); gap: 5mm; justify-content: center; padding: 8mm; direction: ltr; }
        .label { position: relative; width: 50mm; height: 32mm; padding: 1.4mm 2.6mm 1.2mm; overflow: hidden; background: #fff; color: #080808; text-align: center; direction: ltr; outline: .2mm solid rgba(20,20,20,.12); page-break-after: always; break-after: page; }
        .brand { height: 2.8mm; overflow: hidden; font: 500 6.6pt/2.8mm Arial, sans-serif; letter-spacing: 1.15px; text-transform: uppercase; white-space: nowrap; }
        .divider { width: 9mm; height: .2mm; margin: .45mm auto .25mm; background: #151515; }
        .style-label { display: block; margin-bottom: -.35mm; color: #77736e; font: 500 4pt/1.5mm Arial, sans-serif; letter-spacing: .8px; text-transform: uppercase; }
        .model { height: 5.1mm; overflow: hidden; font: 700 14pt/5.1mm "Helvetica Neue", Arial, sans-serif; letter-spacing: .25px; white-space: nowrap; }
        .attributes { display: flex; align-items: baseline; justify-content: center; gap: 1.4mm; height: 3.3mm; margin-top: .15mm; overflow: hidden; font: 600 8pt/3.3mm Arial, sans-serif; white-space: nowrap; }
        .attributes .color { max-width: 27mm; overflow: hidden; text-overflow: ellipsis; }
        .attributes .separator { color: #918d87; font-size: 5pt; }
        .attributes .size { font-size: 9pt; font-weight: 800; }
        .sku { height: 2.4mm; margin-top: .15mm; overflow: hidden; color: #57534e; font: 500 5.7pt/2.4mm "Courier New", monospace; letter-spacing: .15px; text-overflow: ellipsis; white-space: nowrap; }
        .barcode-zone { height: 11.5mm; margin-top: .2mm; padding: .4mm 0 0; background: #fff; }
        .barcode-svg { display: block; width: 44mm; height: 8.8mm; margin: 0 auto; shape-rendering: crispEdges; }
        .barcode-number { height: 2mm; margin-top: -.1mm; font: 500 6.3pt/2mm "Courier New", monospace; letter-spacing: .85px; white-space: nowrap; }
        @media print {
            html, body { width: 50mm; height: 32mm; background: #fff; }
            .toolbar { display: none !important; }
            .labels { display: block; width: 50mm; margin: 0; padding: 0; }
            .label { margin: 0; outline: 0; }
            .label:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><span class="toolbar-count">{{ $labels->count() }} ملصق · 50 × 32 مم</span><button type="button" onclick="window.print()">طباعة</button><button type="button" class="secondary" onclick="window.close()">إغلاق</button></div>
    <main class="labels">
        @foreach($labels as $label)
            <section class="label" data-sku="{{ $label['sku'] }}" data-barcode="{{ $label['barcode'] }}">
                <div class="brand">{{ $label['brand'] }}</div>
                <div class="divider"></div>
                <span class="style-label">STYLE</span>
                <div class="model">{{ $label['model'] }}</div>
                <div class="attributes"><span class="color" dir="auto">{{ $label['color'] }}</span><span class="separator">●</span><span class="size">{{ $label['size'] }}</span></div>
                <div class="sku">{{ $label['sku'] }}</div>
                <div class="barcode-zone">{!! $label['svg'] !!}<div class="barcode-number">{{ $label['barcode'] }}</div></div>
            </section>
        @endforeach
    </main>
    @if($autoPrint)<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
