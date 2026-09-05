{{--
    ورقة الطباعة.

    بلا قائمة ولا ترويسة ولا ثيم: هذه ورقةٌ تُطبَع وتُقصّ. وكل ما
    ليس رمزاً يُهدر حبراً ومساحة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('ورقة رموز المذكرة') }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 12mm;
            background: #fff;
            color: #000;
            font-family: system-ui, "Segoe UI", Tahoma, sans-serif;
        }

        h1 { font-size: 14pt; margin: 0 0 2mm; }
        .note { font-size: 9pt; color: #555; margin: 0 0 6mm; }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4mm;
        }

        .cell {
            border: 1px dashed #999;   /* خطُّ القصّ */
            padding: 3mm;
            text-align: center;
            break-inside: avoid;
        }

        .cell svg { width: 100%; height: auto; }

        .label {
            font-size: 8pt;
            margin-top: 2mm;
            overflow-wrap: anywhere;
        }

        .code {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 8pt;
            color: #555;
            letter-spacing: .5px;
        }

        @media print {
            body { padding: 8mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <h1>{{ site_name() }} — {{ __('رموز المذكرة') }}</h1>

    <p class="note">
        {{ __('قُصّ على الخطوط المتقطّعة، والصق كل رمز في موضعه. والرمز المكتوب تحته يُكتب في الموقع إن لم تعمل الكاميرا.') }}
    </p>

    <p class="note no-print">
        <button type="button" onclick="window.print()">{{ __('اطبع') }}</button>
    </p>

    <div class="grid">
        @foreach($codes as $code)
            <div class="cell">
                {!! $code->svg(150) !!}
                <div class="label">{{ $code->label }}</div>
                <div class="code">{{ $code->code }}</div>
            </div>
        @endforeach
    </div>

</body>
</html>
