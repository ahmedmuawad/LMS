<x-layouts.app :title="__('حجز :reference', ['reference' => $booking->reference])">
<x-site.header />

<main id="main" class="max-w-[760px] mx-auto px-4 sm:px-6 py-8">

    @php
        $tone = match ($booking->status) {
            'cancelled', 'no_show' => 'danger',
            'completed', 'confirmed' => 'success',
            default => 'info',
        };
    @endphp

    <x-ui.page-header :title="__('تفاصيل الحجز')" :subtitle="$booking->service?->title" :back="url('/services')" />

    <div class="surface-card p-5 flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="font-mono text-lg tabular">{{ $booking->reference }}</span>
            <x-ui.badge :tone="$tone">{{ __(App\Modules\Services\Models\Booking::STATUSES[$booking->status] ?? $booking->status) }}</x-ui.badge>
        </div>

        @php
            $start = $booking->startsAtCarbon();

            $rows = [__('الخدمة') => $booking->service?->title ?? '—'];

            if ($start !== null) {
                $rows[__('الموعد')] = $start->translatedFormat('l j F Y — H:i');
                $rows[__('المنطقة الزمنية')] = $booking->timezone ?? '—';
            }

            if ($booking->provider) {
                $rows[__('مقدّم الخدمة')] = $booking->provider->name();
            }

            $rows[__('باسم')] = $booking->customerName();
            $rows[__('المبلغ')] = $booking->price_minor > 0 ? $booking->price()->format() : __('يُحدَّد بعرض سعر');
            $rows[__('طُلب في')] = $booking->created_at?->translatedFormat('j F Y — H:i') ?? '—';
        @endphp

        <x-ui.description-list :items="$rows" />

        @if($booking->meeting_url && in_array($booking->status, ['confirmed', 'in_progress'], true))
            <x-ui.button as="a" :href="$booking->meeting_url" size="lg" class="w-full"
                         target="_blank" rel="noopener noreferrer">{{ __('ادخل الجلسة') }}</x-ui.button>
        @endif

        @if($booking->notes)
            <div>
                <p class="text-sm font-semibold mb-1">{{ __('ملاحظاتك') }}</p>
                <p class="text-sm text-muted leading-relaxed whitespace-pre-line">{{ $booking->notes }}</p>
            </div>
        @endif

        @if(filled($booking->intake))
            <div>
                <p class="text-sm font-semibold mb-2">{{ __('ما أرسلته') }}</p>
                <x-ui.description-list :items="$booking->intake" />
            </div>
        @endif

        @if($booking->status === 'cancelled' && $booking->cancel_reason)
            <x-ui.alert tone="danger" :title="__('سبب الإلغاء')">{{ $booking->cancel_reason }}</x-ui.alert>
        @endif

        @if($errors->has('booking'))
            <x-ui.alert tone="danger">{{ $errors->first('booking') }}</x-ui.alert>
        @endif

        @if(! in_array($booking->status, ['cancelled', 'completed'], true)
            && ($booking->user_id === auth()->id() || auth()->user()?->canAccessPanel()))
            <form method="POST" action="{{ url('/bookings/'.$booking->token.'/cancel') }}"
                  class="flex flex-col sm:flex-row gap-2 pt-2 border-t border-default">
                @csrf
                <x-ui.input name="reason" :placeholder="__('سبب الإلغاء (اختياري)')" maxlength="200" class="flex-1" />
                <x-ui.button type="submit" variant="danger" class="w-full sm:w-auto sm:shrink-0">{{ __('إلغاء الحجز') }}</x-ui.button>
            </form>

            @unless($booking->canCancelFreely())
                <p class="text-2xs text-subtle">{{ __('انتهت مهلة الإلغاء المجاني — قد تُطبَّق رسوم.') }}</p>
            @endunless
        @endif
    </div>
</main>

<x-site.footer />
</x-layouts.app>
