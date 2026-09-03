@props(['title' => null, 'current' => null])
@php
    $nav      = app(App\Core\Admin\Navigation::class)->groups();
    $tenant   = tenant();
    $trial    = $tenant?->onTrial() ? $tenant->trialDaysLeft() : 0;
    $pastDue  = $tenant?->status === 'past_due';
@endphp
<x-layouts.app :title="$title">
<div x-data="{ nav: false }" class="min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">

    {{-- ===== الشريط الجانبي ===== --}}
    <aside class="hidden lg:flex flex-col gap-1 bg-surface border-e border-line h-screen sticky top-0 overflow-y-auto p-3">
        <a href="{{ url('/admin/dashboard') }}" class="flex items-center gap-2.5 px-2 py-3 mb-1">
            <span class="size-9 rounded-md grid place-items-center text-primary-on font-bold shrink-0"
                  style="background-color: var(--sem-primary-hover); background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                  aria-hidden="true">{{ mb_substr((string) ($tenant?->name ?? 'أ'), 0, 1) }}</span>
            <span class="min-w-0">
                <span class="block font-bold text-sm truncate">{{ $tenant?->name ?? config('app.name') }}</span>
                <span class="block text-2xs text-subtle truncate">{{ __('لوحة التحكم') }}</span>
            </span>
        </a>

        @foreach($nav as $group)
            <p class="text-2xs tracking-wider text-subtle font-semibold px-3 pt-3 pb-1">{{ $group['label'] }}</p>
            @foreach($group['items'] as $item)
                @if($item['locked'])
                    <span class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm text-subtle cursor-not-allowed select-none"
                          title="{{ __('يتطلب باقة أعلى') }}">
                        <span aria-hidden="true" class="w-4 text-center opacity-50">{{ $item['icon'] }}</span>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        <span aria-hidden="true" class="text-2xs">🔒</span>
                        <span class="sr-only">{{ __('غير متاح في باقتك الحالية') }}</span>
                    </span>
                @else
                    <a href="{{ $item['url'] }}"
                       @class([
                           'flex items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors',
                           'bg-primary-subtle text-primary font-semibold' => $current === $item['key'],
                           'text-muted hover:bg-surface-sunken hover:text-content' => $current !== $item['key'],
                       ])
                       @if($current === $item['key']) aria-current="page" @endif>
                        <span aria-hidden="true" class="w-4 text-center">{{ $item['icon'] }}</span>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        @endforeach
    </aside>

    {{-- ===== المحتوى ===== --}}
    <div class="min-w-0 flex flex-col">

        {{-- شريط علوي --}}
        <header class="sticky top-0 z-30 bg-surface/95 backdrop-blur border-b border-line px-4 sm:px-6 py-3 flex items-center gap-3">
            <button type="button" @click="nav = true"
                    class="lg:hidden size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                    aria-label="{{ __('فتح القائمة') }}">☰</button>

            <span class="font-semibold text-sm truncate flex-1">{{ $title }}</span>

            <x-ui.theme-toggle />

            <x-ui.dropdown>
                <x-slot:trigger>
                    <button type="button" class="flex items-center gap-2 rounded-full p-0.5 hover:bg-surface-sunken transition-colors"
                            aria-label="{{ __('حساب المستخدم') }}">
                        <x-ui.avatar :name="$tenant?->owner_name ?? 'مستخدم'" size="sm" />
                    </button>
                </x-slot:trigger>
                <x-ui.menu-item icon="☺">{{ __('ملفي الشخصي') }}</x-ui.menu-item>
                <x-ui.menu-item icon="⚙" :href="url('/admin/settings')">{{ __('الإعدادات') }}</x-ui.menu-item>
                <x-ui.menu-item icon="↩" :danger="true">{{ __('تسجيل الخروج') }}</x-ui.menu-item>
            </x-ui.dropdown>
        </header>

        {{-- تنبيهات حالة الاشتراك --}}
        @if($pastDue)
            <div class="px-4 sm:px-6 pt-4">
                <x-ui.alert tone="danger" :title="__('تعذّر تحصيل اشتراكك')">
                    {{ __('منصّتك تعمل الآن بشكل طبيعي لطلابك، لكن لوحة التحكم ستُقفل بعد ٥ أيام إن لم يتم الدفع.') }}
                </x-ui.alert>
            </div>
        @elseif($trial > 0)
            <div class="px-4 sm:px-6 pt-4">
                <x-ui.alert tone="info" :title="trans_choice('بقي :count يوم في تجربتك المجانية|بقيت :count أيام في تجربتك المجانية', $trial, ['count' => $trial])">
                    {{ __('اختر باقتك قبل انتهاء التجربة حتى لا تتوقّف لوحة التحكم.') }}
                </x-ui.alert>
            </div>
        @endif

        <main id="main" class="min-w-0 flex-1 p-4 sm:p-6">{{ $slot }}</main>
    </div>

    {{-- ===== قائمة الموبايل ===== --}}
    <div x-show="nav" x-cloak class="lg:hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="{{ __('القائمة') }}"
         @keydown.escape.window="nav = false">
        <div x-show="nav" x-transition.opacity class="absolute inset-0" style="background: var(--sem-overlay)" @click="nav = false"></div>
        <nav x-show="nav" x-trap.noscroll="nav"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-x-full rtl:-translate-x-full"
             x-transition:enter-end="translate-x-0"
             class="absolute inset-y-0 start-0 w-72 max-w-[85%] bg-surface border-e border-line overflow-y-auto p-3">
            <div class="flex items-center justify-between px-2 py-3 mb-1">
                <span class="font-bold text-sm truncate">{{ $tenant?->name }}</span>
                <button type="button" @click="nav = false" class="size-8 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                        aria-label="{{ __('إغلاق') }}">✕</button>
            </div>
            @foreach($nav as $group)
                <p class="text-2xs tracking-wider text-subtle font-semibold px-3 pt-3 pb-1">{{ $group['label'] }}</p>
                @foreach($group['items'] as $item)
                    <a @if(! $item['locked']) href="{{ $item['url'] }}" @endif
                       @class([
                           'flex items-center gap-2.5 px-3 py-2.5 rounded-md text-sm transition-colors',
                           'bg-primary-subtle text-primary font-semibold' => $current === $item['key'],
                           'text-subtle pointer-events-none' => $item['locked'],
                           'text-muted' => $current !== $item['key'] && ! $item['locked'],
                       ])>
                        <span aria-hidden="true" class="w-4 text-center">{{ $item['icon'] }}</span>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if($item['locked'])<span aria-hidden="true" class="text-2xs">🔒</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </div>
</div>
</x-layouts.app>
