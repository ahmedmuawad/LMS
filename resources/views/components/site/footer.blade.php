@php
    $siteName = setting()->translated('general.site_name') ?: (tenant('name') ?? config('app.name'));

    /*
     | الصفحات الإلزامية تُنشأ مسودّةً ليحرّرها المشترك، فلا يجوز
     | ربطها قبل نشرها: رابط لا يفتح شيئاً في التذييل يوقف الدفع
     | عند مراجعة البوابة قبل أن يوقفه أي شيء آخر.
     */
    $systemPages = tenant() === null
        ? collect()
        : App\Modules\Content\Models\Page::where('is_system', true)
            ->where('status', 'published')
            ->get(['slug', 'title', 'system_key', 'published_at', 'status'])
            ->filter(fn ($page): bool => $page->isLive())
            ->keyBy('system_key');

    $explore = array_values(array_filter([
        ['url' => url('/courses'), 'label' => __('الكورسات'), 'on' => module_enabled('lms')],
        ['url' => url('/services'), 'label' => __('الخدمات'), 'on' => module_enabled('services')],
        ['url' => url('/blog'), 'label' => __('المدونة'), 'on' => module_enabled('blog')],
        ['url' => url('/about'), 'label' => __('من نحن'), 'on' => $systemPages->has('about')],
        ['url' => url('/contact'), 'label' => __('اتصل بنا'), 'on' => $systemPages->has('contact')],
    ], fn (array $l): bool => $l['on']));

    $policies = $systemPages->only(['terms', 'privacy', 'refund', 'faq']);
@endphp
<footer class="border-t border-line mt-12">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <div class="min-w-0">
            <p class="font-bold mb-2">{{ $siteName }}</p>
            <p class="text-muted text-xs leading-relaxed">{{ setting()->translated('general.tagline') }}</p>
        </div>

        <nav aria-label="{{ __('روابط') }}">
            <p class="font-semibold text-xs text-subtle mb-2">{{ __('استكشف') }}</p>
            <ul class="grid gap-1.5">
                @foreach($explore as $link)
                    <li><a href="{{ $link['url'] }}" class="tap-link text-muted hover:text-content transition-colors">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if($policies->isNotEmpty())
            <nav aria-label="{{ __('السياسات') }}">
                <p class="font-semibold text-xs text-subtle mb-2">{{ __('السياسات') }}</p>
                <ul class="grid gap-1.5">
                    @foreach($policies as $page)
                        <li><a href="{{ url('/'.$page->slug) }}" class="tap-link text-muted hover:text-content transition-colors">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

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
