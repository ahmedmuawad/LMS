<x-layouts.app :title="$path->title">
<x-site.header />

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="$path->title" :back="url('/paths')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($path->description)
        <p class="text-muted leading-relaxed mb-6">{{ $path->description }}</p>
    @endif

    @auth
        <x-ui.card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                <span class="text-sm font-semibold">{{ __('تقدّمك في المسار') }}</span>
                <span class="font-mono text-sm tabular">{{ $progress }}%</span>
            </div>

            <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
                <div class="h-full rounded-full bg-primary transition-[width] duration-500"
                     style="width: {{ $progress }}%"></div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4">
                @unless($enrolled)
                    <form method="POST" action="{{ url('/paths/'.$path->slug.'/join') }}">
                        @csrf
                        <x-ui.button type="submit">{{ __('التحق بالمسار') }}</x-ui.button>
                    </form>
                @endunless

                @if($next)
                    {{-- «ابدأ من هنا» أنفع من قائمةٍ يختار منها --}}
                    <x-ui.button :variant="$enrolled ? 'primary' : 'secondary'"
                                 :href="url('/courses/'.$next->slug)">
                        {{ __('التالي: :course', ['course' => $next->title]) }}
                    </x-ui.button>
                @elseif($enrolled)
                    <x-ui.badge tone="success">{{ __('أتممتَ المسار') }}</x-ui.badge>
                @endif
            </div>
        </x-ui.card>
    @endauth

    <x-ui.card :title="__('كورسات المسار')">
        @if($path->items->isEmpty())
            <x-ui.empty :title="__('لم تُضف كورسات بعد')" />
        @else
            <div class="grid gap-1.5">
                @foreach($path->items as $index => $item)
                    @php $open = $unlocked[$item->course_id] ?? true; @endphp

                    <div class="flex flex-wrap items-center gap-3 py-2.5 border-b border-line last:border-0
                                {{ $open ? '' : 'opacity-60' }}">
                        <span class="shrink-0 w-7 h-7 grid place-items-center rounded-full bg-surface-sunken
                                     font-mono text-xs tabular">{{ $index + 1 }}</span>

                        <span class="min-w-0 flex-1 text-sm truncate">
                            {{ $item->course?->title ?? '—' }}
                            @unless($item->is_required)
                                <span class="text-2xs text-subtle">· {{ __('اختياري') }}</span>
                            @endunless
                        </span>

                        @if($item->course === null)
                            —
                        @elseif($open)
                            <x-ui.button size="sm" variant="ghost" :href="url('/courses/'.$item->course->slug)">
                                {{ __('افتح') }}
                            </x-ui.button>
                        @else
                            {{-- المقفول يُقال سببه: القفل الصامت يبدو عطلاً --}}
                            <span class="text-2xs text-subtle shrink-0">{{ __('يُفتح بعد ما قبله') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

</main>

<x-site.footer />
</x-layouts.app>
