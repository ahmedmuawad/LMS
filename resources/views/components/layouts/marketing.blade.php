@props(['title' => null, 'description' => null])
@php
    $brand = config('app.name') === 'Laravel'
        ? config('marketing.brand.name')
        : config('app.name');

    $description ??= config('marketing.brand.description');
    $nav = config('marketing.nav', []);
@endphp

<x-layouts.app :title="$title">

    @push('head')
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $brand }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="canonical" href="{{ url()->current() }}">
        @foreach(config('locales.supported', []) as $code => $locale)
            <link rel="alternate" hreflang="{{ $code }}"
                  href="{{ url($code === config('locales.default') ? '/' : '/'.$code) }}">
        @endforeach
        <script type="application/ld+json">{!! json_encode([
            '@'.'context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $brand,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Learning Management System',
            'operatingSystem' => 'Web',
            'inLanguage' => ['ar', 'en'],
            'description' => $description,
            'url' => url('/'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush

    <header class="sticky top-0 z-30 bg-surface/90 backdrop-blur border-b border-line" x-data="{ open: false }">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-3">

            <a href="{{ url('/') }}" class="flex items-center gap-2.5 min-w-0 shrink-0">
                <span class="size-9 rounded-lg grid place-items-center text-primary-on font-extrabold shadow-sm"
                      style="background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                      aria-hidden="true">{{ mb_substr($brand, 0, 1) }}</span>
                <span class="min-w-0">
                    <span class="block font-display font-extrabold text-[17px] leading-tight truncate">{{ $brand }}</span>
                    <span class="block text-2xs text-subtle leading-tight truncate">{{ config('marketing.brand.tagline') }}</span>
                </span>
            </a>

            <nav class="hidden lg:flex items-center gap-0.5 ms-6 flex-1" aria-label="{{ __('أقسام الصفحة') }}">
                @foreach($nav as $link)
                    <a href="{{ $link['href'] }}"
                       class="px-3 py-2 rounded-md text-sm text-muted hover:bg-surface-sunken hover:text-content transition-colors">{{ __($link['label']) }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2 ms-auto shrink-0">
                <span class="hidden sm:block"><x-ui.theme-toggle /></span>
                <x-ui.button size="sm" variant="ghost" href="#pricing" class="hidden sm:inline-flex">{{ __('الأسعار') }}</x-ui.button>
                <x-ui.button size="sm" href="#start">{{ __('ابدأ مجاناً') }}</x-ui.button>

                <button type="button" @click="open = !open"
                        class="lg:hidden size-10 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                        :aria-expanded="open" aria-label="{{ __('القائمة') }}">☰</button>
            </div>
        </div>

        <nav x-show="open" x-cloak @click="open = false"
             class="lg:hidden border-t border-line px-4 py-2 grid gap-1" aria-label="{{ __('قائمة الموبايل') }}">
            @foreach($nav as $link)
                <a href="{{ $link['href'] }}"
                   class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __($link['label']) }}</a>
            @endforeach
            <div class="px-3 py-2 sm:hidden"><x-ui.theme-toggle /></div>
        </nav>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="border-t border-line bg-surface">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div class="min-w-0">
                <p class="font-display font-extrabold text-base mb-2">{{ $brand }}</p>
                <p class="text-muted text-xs leading-relaxed max-w-[38ch]">{{ config('marketing.brand.description') }}</p>
            </div>

            <nav aria-label="{{ __('المنتج') }}">
                <p class="font-semibold text-xs text-subtle mb-2.5">{{ __('المنتج') }}</p>
                <ul class="grid gap-1.5">
                    @foreach($nav as $link)
                        <li><a href="{{ $link['href'] }}" class="tap-link text-muted hover:text-content transition-colors">{{ __($link['label']) }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('لمن') }}">
                <p class="font-semibold text-xs text-subtle mb-2.5">{{ __('لمن') }}</p>
                <ul class="grid gap-1.5">
                    @foreach(config('platform-modes.modes', []) as $key => $mode)
                        <li><a href="#modes" class="tap-link text-muted hover:text-content transition-colors">{{ $mode['name'][app()->getLocale()] ?? $mode['name']['ar'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="min-w-0">
                <p class="font-semibold text-xs text-subtle mb-2.5">{{ __('تواصل') }}</p>
                <a href="mailto:{{ config('marketing.brand.email') }}"
                   class="tap-link text-muted hover:text-content transition-colors font-mono text-xs break-all">{{ config('marketing.brand.email') }}</a>
                <p class="text-subtle text-xs mt-3 leading-relaxed">{{ __('العربية والإنجليزية · RTL أصيل') }}</p>
            </div>
        </div>

        <div class="border-t border-line py-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-2xs text-subtle">
            <span>© {{ now()->year }} {{ $brand }} — {{ __('كل الحقوق محفوظة') }}</span>
            <a href="{{ url('/terms') }}" class="tap-link hover:text-content transition-colors">{{ __('الشروط') }}</a>
            <a href="{{ url('/privacy') }}" class="tap-link hover:text-content transition-colors">{{ __('الخصوصية') }}</a>
            <a href="{{ url('/refund') }}" class="tap-link hover:text-content transition-colors">{{ __('الاسترداد') }}</a>
        </div>
    </footer>

</x-layouts.app>
