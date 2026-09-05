@php
    $tones = [
        'pending' => 'warning', 'paid' => 'success', 'completed' => 'success',
        'cancelled' => 'neutral', 'refunded' => 'info', 'failed' => 'danger',
    ];
    $labels = [
        'pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'completed' => 'مكتمل',
        'cancelled' => 'ملغى', 'refunded' => 'مسترَدّ', 'failed' => 'فشل',
    ];
@endphp

<x-layouts.student :title="__('طلباتي')" current="orders">

    <x-ui.page-header :title="__('طلباتي')"
                      :subtitle="__('كل ما اشتريته، وحالة كل طلب، وإيصاله.')" />

    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا طلبات بعد')">
                {{ __('يظهر هنا كل ما تشتريه من كورسات وخدمات ومنتجات.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($orders as $order)
                <a href="{{ url('/orders/'.$order->number) }}"
                   class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2 hover:border-primary transition-colors">

                    <div class="min-w-0 flex-1">
                        <p class="font-mono text-sm font-semibold tabular">{{ $order->number }}</p>
                        <p class="text-xs text-muted mt-0.5 truncate">
                            {{ $order->items->pluck('title_snapshot')->filter()->take(2)->implode(' · ') ?: __('طلب') }}
                            @if($order->items->count() > 2)
                                <span class="text-subtle">+{{ $order->items->count() - 2 }}</span>
                            @endif
                        </p>
                        <p class="text-2xs text-subtle font-mono tabular mt-1">
                            {{ $order->placed_at?->translatedFormat('j F Y · g:i a') ?? '—' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="font-mono text-sm font-semibold tabular">{{ $order->total()->format() }}</span>
                        <x-ui.badge :tone="$tones[$order->status] ?? 'neutral'">
                            {{ __($labels[$order->status] ?? $order->status) }}
                        </x-ui.badge>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif

</x-layouts.student>
