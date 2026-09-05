<x-layouts.app :title="$event->title">
<x-site.header />

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="$event->title" :back="url('/events')">
        <x-slot:actions>
            <x-ui.badge :tone="$event->kind === 'holiday' ? 'warning' : 'neutral'">
                {{ $event->kindLabel() }}
            </x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('event')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card class="mb-5">
        <x-ui.description-list :items="array_filter([
            __('الموعد') => $event->whenLabel(),
            __('المكان') => $event->location,
            __('الكورس') => $event->course?->title,
            __('المقاعد') => $event->takesRegistrations()
                ? __(':left من :total', ['left' => $event->seatsLeft(), 'total' => $event->capacity])
                : null,
        ])" />

        @if($event->description)
            <div class="mt-4 pt-4 border-t border-line leading-relaxed whitespace-pre-line text-muted">
                {{ $event->description }}
            </div>
        @endif
    </x-ui.card>

    @if($event->hasPassed())
        <x-ui.alert tone="neutral">{{ __('انتهت هذه الفعالية.') }}</x-ui.alert>

        @if($event->url)
            <div class="mt-4">
                <x-ui.button :href="$event->url" target="_blank" rel="noopener">
                    {{ __('افتح الرابط') }}
                </x-ui.button>
            </div>
        @endif

    @elseif(! $event->takesRegistrations())
        {{-- إعلانٌ لا تسجيل: السعة صفرٌ فلا مقاعد تُحجز --}}
        @if($event->url)
            <x-ui.button :href="$event->url" target="_blank" rel="noopener">
                {{ __('افتح الرابط') }}
            </x-ui.button>
        @endif

    @elseif(auth()->guest())
        <x-ui.card>
            <p class="text-muted text-sm mb-4">{{ __('سجّل دخولك لتحجز مقعدك.') }}</p>
            <x-ui.button :href="url('/login')">{{ __('تسجيل الدخول') }}</x-ui.button>
        </x-ui.card>

    @elseif($registered)
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.badge tone="success">{{ __('مقعدك محجوز') }}</x-ui.badge>

            @if($event->url)
                <x-ui.button :href="$event->url" target="_blank" rel="noopener">
                    {{ __('افتح الرابط') }}
                </x-ui.button>
            @endif

            <form method="POST" action="{{ url('/events/'.$event->slug.'/cancel') }}">
                @csrf
                <x-ui.button variant="ghost" type="submit">{{ __('ألغِ تسجيلي') }}</x-ui.button>
            </form>
        </div>

    @elseif($event->isFull())
        <x-ui.alert tone="warning">{{ __('اكتملت المقاعد.') }}</x-ui.alert>

    @else
        <form method="POST" action="{{ url('/events/'.$event->slug.'/register') }}">
            @csrf
            <x-ui.button type="submit">
                {{ __('احجز مقعدي') }} · <span class="font-mono">{{ $event->seatsLeft() }}</span>
            </x-ui.button>
        </form>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
