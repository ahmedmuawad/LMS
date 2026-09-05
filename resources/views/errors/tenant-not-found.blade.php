{{--
    عنوانٌ لا منصّة عليه.

    كان يردّ ٥٠٠ ويكتب أثراً كاملاً في السجلّ: من أخطأ حرفاً في
    عنوان مدرّسه يرى «خطأ في الخادم» فيظنّ المنصّة معطّلة، والسجلّ
    يمتلئ بما ليس خطأً عندنا.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('لا توجد منصّة على هذا العنوان') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh grid place-items-center bg-surface-sunken text-content p-6">
    <main class="w-full max-w-[520px] surface-card p-8 text-center">
        <p class="text-5xl mb-4" aria-hidden="true">◌</p>

        <h1 class="text-xl font-bold mb-2">{{ __('لا توجد منصّة على هذا العنوان') }}</h1>

        <p class="text-muted leading-relaxed mb-6">
            {{ __('العنوان :host غير مسجّل عندنا. راجع كتابته — أو اطلب الرابط من مدرّسك.', [
                'host' => $host,
            ]) }}
        </p>

        <div class="flex flex-wrap gap-3 justify-center">
            <a href="{{ $home }}"
               class="inline-flex items-center justify-center min-h-11 px-5 rounded-md
                      bg-primary text-primary-on text-sm font-semibold hover:opacity-90 transition-opacity">
                {{ __('الصفحة الرئيسية') }}
            </a>

            <a href="{{ rtrim($home, '/').'/start' }}"
               class="inline-flex items-center justify-center min-h-11 px-5 rounded-md
                      border border-line-strong text-sm font-semibold hover:bg-surface-sunken transition-colors">
                {{ __('أنشئ منصّتك') }}
            </a>
        </div>
    </main>
</body>
</html>
