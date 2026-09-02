@php
    $locale = app()->getLocale();
    $dir    = in_array($locale, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    {{-- منع وميض الوضع الخاطئ قبل تحميل Alpine --}}
    <script>
        (function () {
            try {
                var m = localStorage.getItem('theme');
                if (m === 'dark' || m === 'light') document.documentElement.setAttribute('data-theme', m);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-bg text-content antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:px-4 focus:py-2 focus:rounded-md focus:bg-primary focus:text-primary-on">
        {{ __('تخطَّ إلى المحتوى') }}
    </a>
    {{ $slot }}
    <x-ui.toast />
    @stack('scripts')
</body>
</html>
