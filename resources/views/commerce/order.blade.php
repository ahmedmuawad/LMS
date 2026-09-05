@php
    use App\Modules\Commerce\Models\Order;
    $tones = [
        'pending' => 'neutral', 'awaiting_payment' => 'warning', 'paid' => 'success',
        'processing' => 'info', 'completed' => 'success', 'cancelled' => 'neutral',
        'refunded' => 'warning', 'failed' => 'danger',
    ];
@endphp

<x-layouts.app :title="__('طلب :n', ['n' => $order->number])">
<x-site.header />

{{--
    حدث الشراء يُرسل من صفحة الطلب المدفوع وحدها.

    إرساله من صفحة الشكر بلا شرط يعدّ كل إعادة تحميل شراءً جديداً،
    فتنتفخ أرقام الإعلانات ويُصرف على قناة تبدو أنجح مما هي.
--}}
@if(in_array($order->status, ['paid', 'processing', 'completed'], true))
    <x-analytics.event name="purchase" :data="[
        'transaction_id' => (string) $order->number,
        'currency' => (string) $order->currency,
        'value' => round((int) $order->total_minor / 100, 2),
        'tax' => round((int) ($order->tax_minor ?? 0) / 100, 2),
        'shipping' => round((int) ($order->shipping_minor ?? 0) / 100, 2),
        'items' => $order->items->map(fn ($item) => [
            'item_id' => (string) $item->getKey(),
            'item_name' => $item->title(),
            'price' => round((int) $item->unit_price_minor / 100, 2),
            'quantity' => (int) $item->quantity,
        ])->all(),
    ]" />
@endif

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('gateway')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <x-ui.page-header :title="__('طلب :n', ['n' => $order->number])"
                      :subtitle="display_date($order->placed_at, 'j F Y · h:i A')">
        <x-slot:actions>
            <x-ui.badge :tone="$tones[$order->status] ?? 'neutral'">
                {{ __(Order::STATUSES[$order->status] ?? $order->status) }}
            </x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if($order->status === 'awaiting_payment' && filled($order->notes))
        <x-ui.card :title="__('تعليمات الدفع')" class="mb-4">
            <div class="text-sm leading-relaxed whitespace-pre-line">{{ $order->notes }}</div>
        </x-ui.card>
    @elseif($order->isPaid())
        <x-ui.alert tone="success" :title="__('تم الدفع')" class="mb-4">
            {{ __('ما اشتريته متاح لك الآن — ستجده في «كورساتي».') }}
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('البنود')" :padding="false" class="mb-4">
        <ul class="divide-y divide-[var(--color-line)]">
            @foreach($order->items as $item)
                <li class="px-5 py-3 flex items-start justify-between gap-3 text-sm">
                    <span class="min-w-0">
                        <span class="block">{{ $item->title() }}</span>
                        @if($item->quantity > 1)<span class="text-2xs text-subtle font-mono">× {{ $item->quantity }}</span>@endif
                        @if($item->isFulfilled())
                            <x-ui.badge tone="success" class="mt-1">{{ __('سُلّم') }}</x-ui.badge>
                        @endif
                    </span>
                    <span class="font-mono tabular shrink-0">{{ $item->total()->format() }}</span>
                </li>
            @endforeach
        </ul>

        <x-slot:footer>
            <x-ui.description-list :items="array_filter([
                __('المجموع') => $order->subtotal()->format(),
                $order->discount()->isZero() ? null : __('الخصم') => $order->discount()->isZero() ? null : '− '.$order->discount()->format(),
                $order->shipping()->isZero() ? null : __('الشحن') => $order->shipping()->isZero() ? null : $order->shipping()->format(),
                $order->tax()->isZero() ? null : __('الضريبة') => $order->tax()->isZero() ? null : $order->tax()->format(),
                __('الإجمالي') => $order->total()->format(),
                $order->outstanding()->isZero() ? null : __('المتبقّي') => $order->outstanding()->isZero() ? null : $order->outstanding()->format(),
                $order->refunded()->isZero() ? null : __('المستردّ') => $order->refunded()->isZero() ? null : $order->refunded()->format(),
            ])" />

            {{--
                رمز هيئة الزكاة والضريبة — للسوق السعودي.

                «المرحلة الأولى» تشترط رمزاً على كل فاتورةٍ مبسّطة
                يحمل اسم البائع ورقمه الضريبي والوقت والإجمالي
                والضريبة. يُحسَب عندنا بلا ربطٍ ولا مفاتيح، ويقرؤه
                المفتّش بتطبيق الهيئة.
            --}}
            @php $zatca = app(App\Core\Invoicing\ZatcaQr::class); @endphp
            @if($zatca->enabled())
                <div class="mt-5 pt-5 border-t border-line flex items-center gap-4">
                    <div class="w-[120px] h-[120px] shrink-0 bg-white rounded-md p-1.5 [&>svg]:w-full [&>svg]:h-full">
                        {!! $zatca->svg($order->total(), $order->tax(), $order->placed_at) !!}
                    </div>
                    <p class="text-2xs text-muted leading-relaxed">
                        {{ __('فاتورة ضريبية مبسّطة — يُقرأ هذا الرمز بتطبيق هيئة الزكاة والضريبة والجمارك.') }}
                    </p>
                </div>
            @endif
        </x-slot:footer>
    </x-ui.card>

    @if($order->payments->isNotEmpty())
        <x-ui.card :title="__('الدفعات')">
            <x-ui.timeline :items="$order->payments->map(fn ($p) => [
                'title' => $p->amount()->format().' · '.$p->gateway,
                'meta'  => ($p->gateway_ref ?? '—').' · '.($p->paid_at ?? $p->created_at)?->diffForHumans(),
                'tone'  => $p->succeeded() ? 'success' : ($p->status === 'failed' ? 'danger' : null),
                'body'  => $p->failure_reason,
            ])->all()" />
        </x-ui.card>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
