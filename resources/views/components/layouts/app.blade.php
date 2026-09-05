@props(['title' => null, 'meta' => null])
@php
    $locale = app()->getLocale();
    $dir    = in_array($locale, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';

    // الوضع الذي يفرضه المشترك، إن فرض واحداً — وإلا فاختيار الزائر وحده
    $forcedScheme = setting('appearance.dark_mode');
    $forcedScheme = in_array($forcedScheme, ['light', 'dark'], true) ? $forcedScheme : null;

    /*
     | أعلام حماية المحتوى تُقرأ في الجذر لا في كل صفحة.
     |
     | وتُقرأ من الإعدادات لا تُفترض: مشتركٌ أطفأها لا يُصاب طلابُه
     | بستارٍ يومض، ومشتركٌ شغّلها تعمل في كل شاشة بلا أن يذكرها
     | أحدٌ في قالبه.
     */
    $guardBlur = tenant() !== null && (bool) setting('security.block_screenshot', false);
    $guardCopy = tenant() !== null && (bool) setting('security.block_copy', false);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}"
      @if($guardBlur) data-guard-blur="1" data-guard-print="{{ __('هذه الصفحة محميّة ولا تُطبع.') }}" @endif
      @if($guardCopy) data-guard-copy="1" @endif
      @if($guardBlur) data-guard-message="{{ __('المحتوى مخفيّ — عُد إلى النافذة لمتابعته.') }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoMeta = $meta ?? (tenant() === null ? null : app(App\Core\Seo\Seo::class)->forPage($title));
        $pageTitle = $seoMeta?->title ?: ($title ?? config('app.name'));
    @endphp
    <title>{{ $pageTitle }}</title>
    @if($seoMeta !== null)
        <x-seo.head :meta="$seoMeta" />
    @endif
    {{-- منع وميض الوضع الخاطئ قبل تحميل Alpine --}}
    <script>
        (function () {
            try {
                var m = localStorage.getItem('theme') || {!! json_encode($forcedScheme) !!};
                if (m === 'dark' || m === 'light') document.documentElement.setAttribute('data-theme', m);
            } catch (e) {}
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(tenant())
        {{-- البيان يُولَّد لكل مشترك: الاسم واللون والأيقونة تخصّه --}}
        <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
        <link rel="icon" href="{{ url('/icon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ url('/icon.svg') }}">
        <meta name="theme-color" content="{{ setting('appearance.brand_color') ?: '#0f766e' }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ mb_substr(site_name(), 0, 12) }}">
    @endif
    {{-- الخطوط مستضافة ذاتياً (وثيقة 13.3): لا طلب لخادم خطوط خارجي --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- تخصيص المشترك: يعيد تعريف الطبقة الدلالية وحدها، وبدرجات مضبوطة التباين --}}
    @php $brand = app(App\Core\Theming\BrandCss::class)->render(); @endphp
    @if($brand)<style>{!! $brand !!}</style>@endif
    @if(tenant())<x-analytics.head />@endif
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
