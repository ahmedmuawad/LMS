@php
    /*
     | رأس «لوح الشرح».
     |
     | موقع المدرّس ليس متجراً: أول ما يبحث عنه الزائر هو مَن يُدرّس
     | وماذا يُدرّس، فالاسم والمادة والمرحلة في الصدارة — لا مربّع بحث.
     | وزرّ الاشتراك يبقى ظاهراً في كل تمريرة لأن الصفحة كلها تقود إليه.
     */
    $siteName = site_name();
    $tagline = setting()->translated('general.tagline');
    $me = auth()->user();

    $cart = module_enabled('commerce')
        ? app(App\Modules\Commerce\Actions\CartManager::class)->current(request(), create: false)
        : null;
    $cartCount = $cart?->loadMissing('items')->count() ?? 0;

    // القائمة تتبع الموديولات المفعّلة: رابط لقسم مطفأ يقود إلى 404
    $links = array_values(array_filter([
        ['url' => url('/courses'), 'label' => __('الكورسات'), 'on' => module_enabled('lms')],
        ['url' => url('/services'), 'label' => __('الخدمات'), 'on' => module_enabled('services')],
        ['url' => url('/blog'), 'label' => __('المدونة'), 'on' => module_enabled('blog')],
        ['url' => url('/#groups'), 'label' => __('المجموعات'), 'on' => module_enabled('center')],
        ['url' => url('/#about'), 'label' => __('عن المدرّس'), 'on' => filled(setting('homepage.about'))],
    ], fn (array $l): bool => $l['on']));

    $unread = $me === null ? 0 : App\Core\Notifications\Models\Notification::where('user_id', $me->getKey())
        ->whereNull('read_at')->count();

    $joinUrl = module_enabled('lms') ? url('/courses') : url('/register');
@endphp
<header class="sticky top-0 z-30 bg-surface border-b border-line shadow-sm" x-data="{ open: false }">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-3 flex items-center gap-4 lg:gap-6">

        {{-- الهوية: الحرف الأول بلون المشترك، ثم الاسم وتحته المادة والمرحلة --}}
        <a href="{{ url('/') }}" class="flex items-center gap-3 min-w-0 shrink-0">
            <span class="size-11 rounded-md bg-primary text-primary-on grid place-items-center text-xl font-bold shrink-0"
                  aria-hidden="true">{{ mb_substr($siteName, 0, 1) }}</span>
            {{-- الاسم يبقى على الهاتف مقتطعاً: مربّعُ حرفٍ وحده لا يقول لزائرٍ أين هو --}}
            <span class="min-w-0">
                <span class="block font-bold text-lg leading-tight truncate">{{ $siteName }}</span>
                @if($tagline)
                    <span class="hidden sm:block text-xs text-muted leading-tight truncate">{{ $tagline }}</span>
                @endif
            </span>
        </a>

        <nav class="hidden lg:flex items-center gap-1 ms-auto" aria-label="{{ __('القائمة الرئيسية') }}">
            @foreach($links as $link)
                <a href="{{ $link['url'] }}"
                   class="px-3 py-2 rounded-md font-medium text-content hover:bg-primary-subtle hover:text-primary transition-colors">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 ms-auto lg:ms-0 shrink-0">

            {{-- مبدّل واحد بالنقر: التصميم يعطيه مربّعاً بحجم زرّ لا شريطاً ثلاثياً --}}
            <button type="button" x-data @click="$store.theme.toggle()"
                    class="size-10 rounded-md border border-line-strong text-content grid place-items-center
                           hover:border-primary hover:text-primary transition-colors"
                    aria-label="{{ __('تبديل الوضع الفاتح والداكن') }}">
                <span aria-hidden="true" x-text="$store.theme.isDark() ? '☀' : '☾'">☾</span>
            </button>

            @if($me)
                <a href="{{ url('/notifications') }}"
                   class="relative size-10 rounded-md grid place-items-center text-muted hover:bg-surface-sunken hover:text-content transition-colors"
                   aria-label="{{ trans_choice('{0} لا إشعارات جديدة|{1} إشعار واحد غير مقروء|{2} إشعاران غير مقروءين|[3,10] :count إشعارات غير مقروءة|[11,*] :count إشعاراً غير مقروء', $unread, ['count' => $unread]) }}">
                    <span aria-hidden="true">◔</span>
                    @if($unread > 0)
                        <span class="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-danger text-danger-on
                                     text-[10px] font-bold grid place-items-center font-mono" aria-hidden="true">{{ min($unread, 99) }}</span>
                    @endif
                </a>
            @endif

            @if(module_enabled('commerce'))
                <a href="{{ url('/cart') }}"
                   class="relative size-10 rounded-md grid place-items-center text-muted hover:bg-surface-sunken hover:text-content transition-colors"
                   aria-label="{{ trans_choice('{0} سلتك فارغة|{1} في سلتك عنصر واحد|{2} في سلتك عنصران|[3,10] في سلتك :count عناصر|[11,*] في سلتك :count عنصراً', $cartCount, ['count' => $cartCount]) }}">
                    <span aria-hidden="true">◨</span>
                    @if($cartCount > 0)
                        <span class="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-primary-on
                                     text-[10px] font-bold grid place-items-center font-mono" aria-hidden="true">{{ $cartCount }}</span>
                    @endif
                </a>
            @endif

            @if($me)
                <a href="{{ url('/my-courses') }}"
                   class="hidden sm:inline-flex items-center px-4 py-2.5 rounded-md bg-primary text-primary-on font-semibold
                          hover:bg-primary-hover transition-colors">{{ __('كورساتي') }}</a>
            @else
                <a href="{{ url('/login') }}"
                   class="hidden sm:inline-flex items-center px-3.5 py-2.5 rounded-md border border-line-strong font-semibold
                          hover:border-primary hover:text-primary transition-colors">{{ __('دخول الطالب') }}</a>
                <a href="{{ $joinUrl }}"
                   class="hidden sm:inline-flex items-center px-4 py-2.5 rounded-md bg-primary text-primary-on font-semibold
                          hover:bg-primary-hover transition-colors">{{ __('اشترك الآن') }}</a>
            @endif

            <button type="button" @click="open = !open"
                    class="lg:hidden size-10 grid place-items-center rounded-md border border-line-strong text-content"
                    :aria-expanded="open" aria-label="{{ __('القائمة') }}">☰</button>
        </div>
    </div>

    <nav x-show="open" x-cloak class="lg:hidden border-t border-line px-4 py-2 grid gap-1" aria-label="{{ __('قائمة الموبايل') }}">
        @foreach($links as $link)
            <a href="{{ $link['url'] }}" class="px-3 py-2.5 rounded-md text-muted hover:bg-primary-subtle hover:text-primary">{{ $link['label'] }}</a>
        @endforeach

        @if($me)
            <a href="{{ url('/my-courses') }}" class="px-3 py-2.5 rounded-md text-muted hover:bg-surface-sunken">{{ __('كورساتي') }}</a>
            <a href="{{ url('/my-progress') }}" class="px-3 py-2.5 rounded-md text-muted hover:bg-surface-sunken">{{ __('تقدّمي') }}</a>
            <a href="{{ url('/account') }}" class="px-3 py-2.5 rounded-md text-muted hover:bg-surface-sunken">{{ __('حسابي') }}</a>
        @else
            <a href="{{ url('/login') }}" class="px-3 py-2.5 rounded-md text-muted hover:bg-surface-sunken">{{ __('دخول الطالب') }}</a>
            <a href="{{ $joinUrl }}" class="px-3 py-2.5 rounded-md bg-primary text-primary-on font-semibold text-center">{{ __('اشترك الآن') }}</a>
        @endif
    </nav>
</header>
