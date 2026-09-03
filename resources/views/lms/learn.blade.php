@php
    $itemable = $item->itemable;
    $kind = $item->kind();
    $done = collect($sections)->flatMap(fn ($s) => $s['items'])
        ->firstWhere(fn ($r) => $r['item']->is($item))['completed'] ?? false;
@endphp

<x-layouts.app :title="$item->title().' · '.$course->title">
<div x-data="{ nav: false }" class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1fr)_340px]">

    <div class="min-w-0 flex flex-col">
        <header class="sticky top-0 z-30 bg-surface/95 backdrop-blur border-b border-line px-4 sm:px-6 py-3 flex items-center gap-3">
            <a href="{{ url('/courses/'.$course->slug) }}"
               class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken shrink-0"
               aria-label="{{ __('العودة إلى صفحة الكورس') }}"><span class="flip-rtl" aria-hidden="true">←</span></a>

            <div class="min-w-0 flex-1">
                <p class="text-2xs text-subtle truncate">{{ $course->title }}</p>
                <p class="font-semibold text-sm truncate">{{ $item->title() }}</p>
            </div>

            <span class="font-mono text-xs tabular text-muted shrink-0">{{ $enrollment->progress_percent }}%</span>
            <x-ui.theme-toggle />

            <button type="button" @click="nav = true"
                    class="lg:hidden size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                    aria-label="{{ __('المنهج') }}">☰</button>
        </header>

        <main id="main" class="min-w-0 flex-1 p-4 sm:p-6">
            @if(session('status'))
                <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
            @endif

            @error('quiz')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

            <div class="max-w-[900px] mx-auto">
                @if($kind === 'lesson')
                    <x-lms.player :item="$item" :lesson="$itemable" :course="$course" :enrollment="$enrollment" />
                @elseif($kind === 'quiz')
                    <x-ui.card :title="$itemable->title">
                        @if($itemable->description)<p class="text-muted mb-4">{{ $itemable->description }}</p>@endif

                        <x-ui.description-list :items="array_filter([
                            __('الزمن') => (int) $itemable->time_limit_minutes === 0 ? __('بلا وقت') : $itemable->time_limit_minutes.' '.__('دقيقة'),
                            __('نسبة النجاح') => $itemable->passing_percentage.'%',
                            __('المحاولات') => (int) $itemable->max_attempts === 0 ? __('بلا حد') : $itemable->max_attempts,
                        ])" />

                        <form method="POST" action="{{ url('/learn/'.$course->slug.'/quiz/'.$item->getKey().'/start') }}" class="mt-4">
                            @csrf
                            <x-ui.button type="submit">{{ __('ابدأ الاختبار') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @else
                    <x-ui.card :title="$itemable->title">
                        @if($itemable->instructions)
                            <div class="text-muted leading-relaxed whitespace-pre-line mb-4">{{ $itemable->instructions }}</div>
                        @endif
                        <x-ui.description-list :items="[
                            __('الدرجة العظمى') => $itemable->max_marks,
                            __('درجة النجاح') => $itemable->passing_marks,
                            __('التسليم المتأخر') => $itemable->allow_late ? __('مقبول') : __('غير مقبول'),
                        ]" />
                    </x-ui.card>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3 mt-6">
                    @if($neighbours['prev'])
                        <x-ui.button variant="secondary" size="sm"
                                     :href="url('/learn/'.$course->slug.'/'.$neighbours['prev']['item']->getKey())">
                            <span class="flip-rtl" aria-hidden="true">←</span> {{ __('السابق') }}
                        </x-ui.button>
                    @else
                        <span></span>
                    @endif

                    <div class="flex items-center gap-2">
                        @unless($done)
                            <form method="POST" action="{{ url('/learn/'.$course->slug.'/'.$item->getKey().'/complete') }}">
                                @csrf
                                <x-ui.button type="submit" size="sm">{{ __('أنهيت هذا') }}</x-ui.button>
                            </form>
                        @else
                            <x-ui.badge tone="success">{{ __('مكتمل') }}</x-ui.badge>
                        @endunless

                        @if($neighbours['next'] && ! $neighbours['next']['locked'])
                            <x-ui.button variant="secondary" size="sm"
                                         :href="url('/learn/'.$course->slug.'/'.$neighbours['next']['item']->getKey())">
                                {{ __('التالي') }} <span class="flip-rtl" aria-hidden="true">→</span>
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </main>
    </div>

    {{-- المنهج: عمود على الشاشات الكبيرة، درج على الصغيرة --}}
    <aside class="hidden lg:block border-s border-line bg-surface h-screen sticky top-0 overflow-y-auto">
        <x-lms.curriculum-list :sections="$sections" :course="$course" :current="$item" :enrollment="$enrollment" />
    </aside>

    <div x-show="nav" x-cloak class="lg:hidden fixed inset-0 z-50" role="dialog" aria-modal="true"
         aria-label="{{ __('المنهج') }}" @keydown.escape.window="nav = false">
        <div x-show="nav" x-transition.opacity class="absolute inset-0" style="background: var(--sem-overlay)" @click="nav = false"></div>
        <div x-show="nav" x-trap.noscroll="nav"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-x-full rtl:-translate-x-full"
             x-transition:enter-end="translate-x-0"
             class="absolute inset-y-0 end-0 w-80 max-w-[88%] bg-surface border-s border-line overflow-y-auto">
            <div class="flex items-center justify-between px-4 py-3 border-b border-line">
                <span class="font-bold text-sm">{{ __('المنهج') }}</span>
                <button type="button" @click="nav = false" class="size-8 grid place-items-center rounded-md text-muted hover:bg-surface-sunken"
                        aria-label="{{ __('إغلاق') }}">✕</button>
            </div>
            <x-lms.curriculum-list :sections="$sections" :course="$course" :current="$item" :enrollment="$enrollment" />
        </div>
    </div>
</div>
</x-layouts.app>
