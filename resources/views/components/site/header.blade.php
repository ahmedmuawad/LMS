@php
    $siteName = setting()->translated('general.site_name') ?: (tenant('name') ?? config('app.name'));
    $me = auth()->user();
@endphp
<header class="sticky top-0 z-30 bg-surface/95 backdrop-blur border-b border-line" x-data="{ open: false }">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-3">
        <a href="{{ url('/') }}" class="font-bold truncate min-w-0 flex items-center gap-2">
            <span class="size-8 rounded-md grid place-items-center text-primary-on text-sm shrink-0"
                  style="background-color: var(--sem-primary)" aria-hidden="true">{{ mb_substr($siteName, 0, 1) }}</span>
            <span class="truncate">{{ $siteName }}</span>
        </a>

        <nav class="hidden md:flex items-center gap-1 ms-4 flex-1" aria-label="{{ __('القائمة الرئيسية') }}">
            <a href="{{ url('/courses') }}" class="px-3 py-2 rounded-md text-sm text-muted hover:bg-surface-sunken hover:text-content transition-colors">{{ __('الكورسات') }}</a>
            <a href="{{ url('/blog') }}" class="px-3 py-2 rounded-md text-sm text-muted hover:bg-surface-sunken hover:text-content transition-colors">{{ __('المدونة') }}</a>
        </nav>

        <div class="flex items-center gap-2 ms-auto">
            <x-ui.theme-toggle />

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
        <a href="{{ url('/courses') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('الكورسات') }}</a>
        <a href="{{ url('/blog') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('المدونة') }}</a>
        @if($me)
            <a href="{{ url('/my-courses') }}" class="px-3 py-2.5 rounded-md text-sm text-muted hover:bg-surface-sunken">{{ __('كورساتي') }}</a>
        @endif
    </nav>
</header>
