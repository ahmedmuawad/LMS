@php
    $siteName = setting()->translated('general.site_name') ?: (tenant('name') ?? config('app.name'));
    $me = auth()->user();
    $cart = app(App\Modules\Commerce\Actions\CartManager::class)->current(request(), create: false);
    $cartCount = $cart?->loadMissing('items')->count() ?? 0;

    // القائمة تتبع الموديولات المفعّلة: رابط لقسم مطفأ يقود إلى 404
    $links = array_values(array_filter([
        ['url' => url('/courses'), 'label' => __('الكورسات'), 'on' => module_enabled('lms')],
        ['url' => url('/services'), 'label' => __('الخدمات'), 'on' => module_enabled('services')],
        ['url' => url('/blog'), 'label' => __('المدونة'), 'on' => module_enabled('blog')],
    ], fn (array $l): bool => $l['on']));

    $unread = $me === null ? 0 : App\Core\Notifications\Models\Notification::where('user_id', $me->getKey())
        ->whereNull('read_at')->count();
@endphp
<header class="sticky top-0 z-30 bg-surface/95 backdrop-blur border-b border-line" x-data="{ open: false }">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-3">
        <a href="{{ url('/') }}" class="font-bold truncate min-w-0 flex items-center gap-2">
            <span class="size-8 rounded-md grid place-items-center text-primary-on text-sm shrink-0"
                  style="background-color: var(--sem-primary)" aria-hidden="true">{{ mb_substr($siteName, 0, 1) }}</span>
            <span class="truncate">{{ $siteName }}</span>
        </a>

        <nav class="hidden md:flex items-center gap-1 ms-4 flex-1" aria-label="{{ __('القائمة الرئيسية') }}">
            @foreach($links as $link)
                <a href="{{ $link['url'] }}" class="px-3 py-2 rounded-md text-sm text-muted hover:bg-surface-sunken hover:text-content transition-colors">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 ms-auto shrink-0">
            {{-- مبدّل الوضع ثلاثي الأزرار أعرض من أن يشارك الشاشة الصغيرة؛ مكانه القائمة --}}
            <span class="hidden sm:block"><x-ui.theme-toggle /></span>

            @if($me)
                <a href="{{ url('/notifications') }}" class="relative size-10 grid place-items-center rounded-md text-muted hover:bg-surface-sunken hover:text-content transition-colors"
                   aria-label="{{ trans_choice('{0} لا إشعارات جديدة|{1} إشعار واحد غير مقروء|{2} إشعاران غير مقروءين|[3,10] :count إشعارات غير مقروءة|[11,*] :count إشعاراً غير مقروء', $unread, ['count' => $unread]) }}">
                    <span aria-hidden="true">◔</span>
                    @if($unread > 0)
                        <span class="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-danger text-danger-on
                                     text-[10px] font-bold grid place-items-center font-mono" aria-hidden="true">{{ min($unread, 99) }}</span>
                    @endif
                </a>
            @endif

            <a href="{{ url('/cart') }}" class="relative size-10 grid place-items-center rounded-md text-muted hover:bg-surface-sunken hover:text-content transition-colors"
               aria-label="{{ trans_choice('{0} سلتك فارغة|{1} في سلتك عنصر واحد|{2} في سلتك عنصران|[3,10] في سلتك :count عناصر|[11,*] في سلتك :count عنصراً', $cartCount, ['count' => $cartCount]) }}">
                <span aria-hidden="true">◨</span>
                @if($cartCount > 0)
                    <span class="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-primary-on
                                 text-[10px] font-bold grid place-items-center font-mono" aria-hidden="true">{{ $cartCount }}</span>
                @endif
            </a>

            @if($me)
                <x-ui.button size="sm" variant="secondary" :href="url('/my-courses')">{{ __('كورساتي') }}</x-ui.button>
            @else
                <x-ui.button size="sm" variant="ghost" :href="url('/login')">{{ __('دخول') }}</x-ui.button>
            @endif

            <button type="button" @click="open = !open"
                    class="md:hidden size-10 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                    :aria-expanded="open" aria-label="{{ __('القائمة') }}">☰</button>
        </div>
    </div>

    <nav x-show="open" x-cloak class="md:hidden border-t border-line px-4 py-2 grid gap-1" aria-label="{{ __('قائمة الموبايل') }}">
        @foreach($links as $link)
            <a href="{{ $link['url'] }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ $link['label'] }}</a>
        @endforeach
        @if($me)
            <a href="{{ url('/my-courses') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('كورساتي') }}</a>
            @if(module_enabled('bookings'))
                <a href="{{ url('/my-bookings') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('حجوزاتي') }}</a>
            @endif
            <a href="{{ url('/wallet') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('محفظتي') }}</a>
            <a href="{{ url('/account/notifications') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('تفضيلات الإشعارات') }}</a>
        @endif
        <div class="px-3 py-2 sm:hidden"><x-ui.theme-toggle /></div>
    </nav>
</header>
