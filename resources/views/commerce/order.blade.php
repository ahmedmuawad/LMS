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

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('gateway')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <x-ui.page-header :title="__('طلب :n', ['n' => $order->number])"
                      :subtitle="$order->placed_at?->translatedFormat('j F Y · h:i A')">
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
