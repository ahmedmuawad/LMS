@php
    /*
     | تذييل «لوح الشرح» — لوحٌ داكن يقفل الصفحة.
     |
     | الدور `spot` يبقى داكناً في الوضعين، فلا ينقلب التذييل أبيض
     | في الوضع الداكن ويكسر النهاية. ونصّه من `on-spot` معه.
     */
    $siteName = site_name();
    $tagline = setting()->translated('general.tagline');
    $phone = setting('homepage.phone') ?: setting('general.phone');
    $whatsapp = preg_replace('/\D+/', '', (string) setting('homepage.whatsapp'));
    $address = setting('general.address');

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
        ['url' => url('/#groups'), 'label' => __('المجموعات'), 'on' => module_enabled('center')],
        ['url' => url('/services'), 'label' => __('الخدمات'), 'on' => module_enabled('services')],
        ['url' => url('/blog'), 'label' => __('المدونة'), 'on' => module_enabled('blog')],
        ['url' => url('/about'), 'label' => __('من نحن'), 'on' => $systemPages->has('about')],
        ['url' => url('/contact'), 'label' => __('اتصل بنا'), 'on' => $systemPages->has('contact')],
    ], fn (array $l): bool => $l['on']));

    $student = array_values(array_filter([
        ['url' => url('/login'), 'label' => __('دخول'), 'on' => auth()->guest()],
        ['url' => url('/my-courses'), 'label' => __('كورساتي'), 'on' => auth()->check() && module_enabled('lms')],
        ['url' => url('/my-progress'), 'label' => __('تقدّمي'), 'on' => auth()->check()],
        // البوابة خلف تسجيل الدخول، ورابطها هنا هو ما يدلّ وليّ الأمر عليها
        ['url' => url('/guardian'), 'label' => __('بوابة وليّ الأمر'), 'on' => module_enabled('parent-portal')],
    ], fn (array $l): bool => $l['on']));

    $policies = $systemPages->only(['terms', 'privacy', 'refund', 'faq']);
@endphp
<footer class="bg-spot text-on-spot mt-16">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-14 pb-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">

        <div class="min-w-0">
            <p class="font-bold text-xl mb-2.5 text-on-spot">{{ $siteName }}</p>
            @if($tagline)
                <p class="text-on-spot-muted leading-relaxed">{{ $tagline }}</p>
            @endif
        </div>

        @if($explore !== [])
            <nav aria-label="{{ __('الموقع') }}" class="min-w-0">
                <p class="font-bold mb-3 text-on-spot">{{ __('الموقع') }}</p>
                <ul class="grid gap-2">
                    @foreach($explore as $link)
                        <li><a href="{{ $link['url'] }}" class="tap-link text-on-spot-muted hover:text-on-spot transition-colors">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if($student !== [])
            <nav aria-label="{{ __('للطالب') }}" class="min-w-0">
                <p class="font-bold mb-3 text-on-spot">{{ __('للطالب') }}</p>
                <ul class="grid gap-2">
                    @foreach($student as $link)
                        <li><a href="{{ $link['url'] }}" class="tap-link text-on-spot-muted hover:text-on-spot transition-colors">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="min-w-0">
            <p class="font-bold mb-3 text-on-spot">{{ __('تواصل') }}</p>
            <div class="grid gap-2 text-on-spot-muted">
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"
                       class="tap-link hover:text-on-spot transition-colors">{{ __('واتساب') }} <span class="font-mono tabular">{{ setting('homepage.whatsapp') }}</span></a>
                @endif
                @if($phone)
                    <a href="tel:{{ $phone }}" class="tap-link font-mono tabular hover:text-on-spot transition-colors">{{ $phone }}</a>
                @endif
                @if($address)
                    <p class="leading-relaxed">{{ $address }}</p>
                @endif
            </div>

            @if($policies->isNotEmpty())
                <nav aria-label="{{ __('السياسات') }}" class="mt-5">
                    <p class="font-bold mb-2 text-on-spot text-sm">{{ __('السياسات') }}</p>
                    <ul class="grid gap-1.5 text-sm">
                        @foreach($policies as $page)
                            <li><a href="{{ url('/'.$page->slug) }}" class="tap-link text-on-spot-muted hover:text-on-spot transition-colors">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>

    <div class="border-t border-spot-line">
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 text-sm text-on-spot-muted">
            © {{ now()->year }} {{ $siteName }} — {{ __('كل الحقوق محفوظة') }}
        </div>
    </div>
</footer>
