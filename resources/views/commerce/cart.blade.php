<x-layouts.app :title="__('سلتك')">
<x-site.header />

<main id="main" class="max-w-[1100px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('سلتك')"
                      :subtitle="trans_choice('{0} فارغة|{1} عنصر واحد|{2} عنصران|[3,10] :count عناصر|[11,*] :count عنصراً', $cart->count(), ['count' => $cart->count()])" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('cart')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    @if($cart->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('سلتك فارغة')">
                {{ __('تصفّح الكورسات وأضف ما يناسبك — بعضها مجاني تماماً.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

            <x-ui.card :padding="false">
                <ul class="divide-y divide-[var(--color-line)]">
                    @foreach($cart->items as $item)
                        <li class="p-4 flex flex-wrap items-center gap-4">
                            <div class="size-16 rounded-md bg-surface-sunken shrink-0 overflow-hidden grid place-items-center">
                                @if($item->product?->cover_path)
                                    <img src="{{ $item->product->cover_path }}" alt="" class="size-full object-cover">
                                @else
                                    <span class="text-subtle" aria-hidden="true">▤</span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm truncate">{{ $item->product?->title }}</p>
                                @if($item->variant)
                                    <p class="text-2xs text-subtle">{{ $item->variant->label() }}</p>
                                @endif
                                <p class="text-xs text-muted font-mono mt-0.5">{{ $item->unitPrice()->format() }}</p>
                            </div>

                            @if(in_array($item->product?->type, ['physical', 'digital'], true))
                                <form method="POST" action="{{ url('/cart/items/'.$item->id) }}" class="flex items-center gap-1.5 shrink-0">
                                    @csrf @method('PUT')
                                    <label class="sr-only" for="q{{ $item->id }}">{{ __('الكمية') }}</label>
                                    <input type="number" name="quantity" id="q{{ $item->id }}" min="0" max="99" value="{{ $item->quantity }}"
                                           class="w-16 bg-surface border border-line-strong rounded-md px-2 py-2 font-mono text-sm">
                                    <x-ui.button size="sm" variant="ghost" type="submit">{{ __('حدّث') }}</x-ui.button>
                                </form>
                            @endif

                            <span class="font-mono text-sm tabular shrink-0">{{ $item->total()->format() }}</span>

                            <form method="POST" action="{{ url('/cart/items/'.$item->id) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <x-ui.button size="sm" variant="ghost" type="submit"
                                             :aria-label="__('إزالة :item', ['item' => $item->product?->title])">✕</x-ui.button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div class="grid gap-4 min-w-0">
                <x-ui.card :title="__('الملخّص')">
                    <x-ui.description-list :items="array_filter([
                        __('المجموع') => $totals['subtotal']->format(),
                        $totals['discount']->isZero() ? null : __('الخصم') => $totals['discount']->isZero() ? null : '− '.$totals['discount']->format(),
                        $totals['shipping']->isZero() ? null : __('الشحن') => $totals['shipping']->isZero() ? null : $totals['shipping']->format(),
                        $totals['tax']->isZero() ? null : __('الضريبة') => $totals['tax']->isZero() ? null : $totals['tax']->format(),
                        __('الإجمالي') => $totals['total']->format(),
                    ])" />

                    <x-ui.button :href="url('/checkout')" class="w-full mt-4">{{ __('إتمام الشراء') }}</x-ui.button>
                </x-ui.card>

                <x-ui.card :title="__('كوبون خصم')">
                    @error('coupon')<x-ui.alert tone="danger" class="mb-3">{{ $message }}</x-ui.alert>@enderror

                    @if($cart->coupon)
                        <div class="flex items-center justify-between gap-2">
                            <x-ui.badge tone="success">{{ $cart->coupon->code }}</x-ui.badge>
                            <form method="POST" action="{{ url('/cart/coupon') }}">
                                @csrf @method('DELETE')
                                <x-ui.button size="sm" variant="ghost" type="submit">{{ __('إزالة') }}</x-ui.button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ url('/cart/coupon') }}" class="flex items-end gap-2">
                            @csrf
                            <x-ui.field :label="__('الكود')" for="code" class="mb-0 flex-1">
                                <x-ui.input name="code" id="code" class="font-mono uppercase" :placeholder="__('مثال: WELCOME20')" />
                            </x-ui.field>
                            <x-ui.button size="sm" type="submit" class="h-11">{{ __('تطبيق') }}</x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            </div>
        </div>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
