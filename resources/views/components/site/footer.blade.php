@php $siteName = setting()->translated('general.site_name') ?: (tenant('name') ?? config('app.name')); @endphp
<footer class="border-t border-line mt-12">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <div class="min-w-0">
            <p class="font-bold mb-2">{{ $siteName }}</p>
            <p class="text-muted text-xs leading-relaxed">{{ setting()->translated('general.tagline') }}</p>
        </div>

        <nav aria-label="{{ __('روابط') }}">
            <p class="font-semibold text-xs text-subtle mb-2">{{ __('استكشف') }}</p>
            <ul class="grid gap-1.5">
                <li><a href="{{ url('/courses') }}" class="tap-link text-muted hover:text-content transition-colors">{{ __('الكورسات') }}</a></li>
                <li><a href="{{ url('/blog') }}" class="tap-link text-muted hover:text-content transition-colors">{{ __('المدونة') }}</a></li>
            </ul>
        </nav>

        <nav aria-label="{{ __('السياسات') }}">
            <p class="font-semibold text-xs text-subtle mb-2">{{ __('السياسات') }}</p>
            <ul class="grid gap-1.5">
                <li><a href="{{ url('/page/terms') }}" class="tap-link text-muted hover:text-content transition-colors">{{ __('الشروط والأحكام') }}</a></li>
                <li><a href="{{ url('/page/privacy') }}" class="tap-link text-muted hover:text-content transition-colors">{{ __('سياسة الخصوصية') }}</a></li>
                <li><a href="{{ url('/page/refund') }}" class="tap-link text-muted hover:text-content transition-colors">{{ __('سياسة الاسترداد') }}</a></li>
            </ul>
        </nav>

        <div class="min-w-0">
            <p class="font-semibold text-xs text-subtle mb-2">{{ __('تواصل') }}</p>
            @if(setting('general.admin_email'))
                <p class="text-muted text-xs font-mono break-all">{{ setting('general.admin_email') }}</p>
            @endif
            @if(setting('general.phone'))
                <p class="text-muted text-xs font-mono mt-1">{{ setting('general.phone') }}</p>
            @endif
        </div>
    </div>

    <div class="border-t border-line py-4 text-center text-2xs text-subtle">
        © {{ now()->year }} {{ $siteName }}
    </div>
</footer>
