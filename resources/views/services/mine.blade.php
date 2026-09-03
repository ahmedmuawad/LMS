<x-layouts.app :title="__('حجوزاتي')">
<x-site.header />

<main id="main" class="max-w-[900px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('حجوزاتي')"
                      :subtitle="trans_choice('{0} لا حجوزات|{1} حجز واحد|{2} حجزان|[3,10] :count حجوزات|[11,*] :count حجزاً', $bookings->total(), ['count' => $bookings->total()])" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if($bookings->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا حجوزات بعد')">
                {{ __('تصفّح الخدمات واحجز أول موعد.') }}
                <x-slot:action>
                    <x-ui.button as="a" :href="url('/services')">{{ __('تصفّح الخدمات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <ul class="flex flex-col gap-3">
            @foreach($bookings as $booking)
                @php
                    $tone = match ($booking->status) {
                        'cancelled', 'no_show' => 'danger',
                        'completed', 'confirmed' => 'success',
                        default => 'info',
                    };
                    $start = $booking->startsAtCarbon();
                @endphp
                <li class="surface-card p-4 flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate">{{ $booking->service?->title ?? __('خدمة محذوفة') }}</p>
                        <p class="text-2xs text-subtle font-mono mt-0.5">
                            {{ $booking->reference }}
                            @if($start) · {{ $start->translatedFormat('j F Y — H:i') }} @endif
                        </p>
                    </div>

                    <x-ui.badge :tone="$tone">{{ __(App\Modules\Services\Models\Booking::STATUSES[$booking->status] ?? $booking->status) }}</x-ui.badge>

                    <x-ui.button as="a" :href="url('/bookings/'.$booking->token)" size="sm" variant="secondary"
                                 class="w-full sm:w-auto sm:shrink-0">
                        {{ __('التفاصيل') }}
                    </x-ui.button>
                </li>
            @endforeach
        </ul>

        @if($bookings->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$bookings->currentPage()" :last="$bookings->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
