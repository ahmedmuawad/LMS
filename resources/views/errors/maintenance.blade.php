{{--
    صفحة الصيانة.

    بلا قائمة ولا سلة ولا روابط: من يراها لا يستطيع فعل شيء، وكل
    رابطٍ يقود إلى الصفحة نفسها فيبدو الموقع مكسوراً لا مغلقاً.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ site_name() }} — {{ __('صيانة') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh grid place-items-center bg-surface-sunken text-content p-6">
    <main class="w-full max-w-[480px] surface-card p-8 text-center">
        <p class="text-5xl mb-4" aria-hidden="true">⚙</p>

        <h1 class="text-xl font-bold mb-3">{{ site_name() }}</h1>

        <p class="text-muted leading-relaxed whitespace-pre-line">{{ $message }}</p>
    </main>
</body>
</html>
