<x-layouts.app :title="__('التقويم')">
<x-site.header />

<main id="main" class="max-w-[1000px] mx-auto px-4 sm:px-6 py-8">

        <x-ui.page-header :title="__('التقويم')"
                          :subtitle="__('ندواتنا وورشنا ومواعيد امتحاناتنا وإجازاتنا.')" />

        @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
        @error('event')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

        @if($upcoming->isEmpty() && $past->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا فعاليات بعد')">
                    {{ __('يظهر هنا كل موعد يعنيك: ندوة، ورشة، يوم امتحان، إجازة.') }}
                </x-ui.empty>
            </x-ui.card>
        @else
            @if($upcoming->isNotEmpty())
                <div class="grid gap-3 mb-10">
                    @foreach($upcoming as $event)
                        <a href="{{ url('/events/'.$event->slug) }}"
                           class="surface-card p-4 sm:p-5 flex flex-wrap items-start gap-4
                                  hover:border-primary transition-colors tap-link">

                            {{-- التاريخ مربّعاً: العين تمسح التواريخ قبل العناوين --}}
                            <div class="shrink-0 text-center rounded-md bg-surface-sunken px-3 py-2 min-w-[64px]">
                                <div class="text-xl font-bold leading-none">{{ $event->starts_at?->format('j') }}</div>
                                <div class="text-2xs text-subtle mt-1">{{ $event->starts_at?->translatedFormat('M') }}</div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <x-ui.badge :tone="$event->kind === 'holiday' ? 'warning' : 'neutral'">
                                        {{ $event->kindLabel() }}
                                    </x-ui.badge>

                                    @if($mine->contains($event->id))
                                        <x-ui.badge tone="success">{{ __('مسجَّل') }}</x-ui.badge>
                                    @elseif($event->isFull())
                                        <x-ui.badge tone="danger">{{ __('اكتملت المقاعد') }}</x-ui.badge>
                                    @elseif($event->takesRegistrations())
                                        <x-ui.badge tone="info">
                                            {{ __('بقي :n مقعداً', ['n' => $event->seatsLeft()]) }}
                                        </x-ui.badge>
                                    @endif
                                </div>

                                <h3 class="font-bold truncate">{{ $event->title }}</h3>

                                <p class="text-xs text-muted mt-1">
                                    {{ $event->whenLabel() }}
                                    @if($event->location) · {{ $event->location }} @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($past->isNotEmpty())
                <h2 class="text-sm font-bold text-subtle mb-3">{{ __('ما مضى') }}</h2>

                <div class="grid gap-2">
                    @foreach($past as $event)
                        <a href="{{ url('/events/'.$event->slug) }}"
                           class="flex flex-wrap items-center gap-3 py-2.5 px-3 rounded-md
                                  hover:bg-surface-sunken transition-colors tap-link opacity-75">
                            <span class="text-2xs text-subtle font-mono tabular shrink-0">
                                {{ $event->starts_at?->translatedFormat('j M') }}
                            </span>
                            <span class="min-w-0 flex-1 text-sm truncate">{{ $event->title }}</span>
                            <span class="text-2xs text-subtle shrink-0">{{ $event->kindLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif

</main>

<x-site.footer />
</x-layouts.app>
