@php
    $tones = [
        'pending' => 'warning', 'confirmed' => 'success', 'in_progress' => 'info',
        'completed' => 'success', 'cancelled' => 'neutral', 'no_show' => 'danger',
    ];
    $labels = [
        'pending' => 'بانتظار التأكيد', 'confirmed' => 'مؤكّد', 'in_progress' => 'جارٍ',
        'completed' => 'مكتمل', 'cancelled' => 'ملغى', 'no_show' => 'لم تحضر',
    ];
@endphp

<x-layouts.student :title="__('طلبات خدماتي')" current="service-requests">

    <x-ui.page-header :title="__('طلبات خدماتي')"
                      :subtitle="__('ما طلبته من خدمات، وحالة كل طلب، ومخرجاته.')" />

    @if($bookings->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا طلبات خدمات')">
                {{ __('تصفّح الخدمات المتاحة واطلب ما يناسبك.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/services')">{{ __('تصفّح الخدمات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($bookings as $booking)
                <div class="surface-card p-4 grid gap-3">

                    <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">{{ $booking->service?->title ?? __('خدمة') }}</p>
                            <p class="text-xs text-muted font-mono tabular mt-0.5">
                                {{ $booking->reference }}
                                @if($booking->date)
                                    · {{ $booking->startsAtCarbon()?->translatedFormat('j F Y · g:i a') }}
                                @endif
                            </p>
                            @if($booking->provider)
                                <p class="text-2xs text-subtle mt-1">{{ $booking->provider->name() }}</p>
                            @endif
                        </div>

                        <x-ui.badge :tone="$tones[$booking->status] ?? 'neutral'">
                            {{ __($labels[$booking->status] ?? $booking->status) }}
                        </x-ui.badge>
                    </div>

                    {{-- المخرجات هي ما دفع الطالب لأجله: تُعرض في البطاقة لا خلف نقرة --}}
                    @if(filled($booking->deliverables))
                        <div class="rounded-md bg-surface-sunken p-3 grid gap-1.5">
                            <p class="text-2xs font-semibold text-subtle">{{ __('المخرجات') }}</p>
                            @foreach($booking->deliverables as $deliverable)
                                <p class="text-xs text-muted">
                                    · {{ is_array($deliverable) ? ($deliverable['title'] ?? '') : $deliverable }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="secondary"
                                     :href="url('/bookings/'.$booking->token)">{{ __('تفاصيل الطلب') }}</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    @endif

</x-layouts.student>
