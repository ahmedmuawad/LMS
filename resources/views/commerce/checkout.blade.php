<x-layouts.app :title="__('إتمام الشراء')">
<x-site.header />

<x-analytics.event name="begin_checkout" :data="[
    'currency' => (string) $cart->currency,
    'value' => round($totals['total']->minor / 100, 2),
    'items' => $cart->items->map(fn ($item) => [
        'item_id' => (string) $item->product_id,
        'item_name' => (string) ($item->product?->title ?? ''),
        'price' => round($item->unitPrice()->minor / 100, 2),
        'quantity' => (int) $item->quantity,
    ])->all(),
]" />

<main id="main" class="max-w-[1000px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('إتمام الشراء')" :back="url('/cart')" />

    @error('cart')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @error('gateway')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <form method="POST" action="{{ url('/checkout') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">
        @csrf

        <div class="grid gap-4 min-w-0">
            @guest
                <x-ui.card :title="__('بياناتك')">
                    <x-ui.field :label="__('الاسم')" for="name" :error="$errors->first('name')">
                        <x-ui.input name="name" id="name" value="{{ old('name') }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('البريد الإلكتروني')" for="email" :required="true"
                                :error="$errors->first('email')"
                                :hint="__('سيصلك عليه إيصال الشراء ورابط المحتوى.')">
                        <x-ui.input name="email" id="email" type="email" value="{{ old('email') }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('الهاتف')" for="phone" :error="$errors->first('phone')">
                        <x-ui.input name="phone" id="phone" type="tel" value="{{ old('phone') }}" />
                    </x-ui.field>
                </x-ui.card>
            @endguest

            <x-ui.card :title="__('وسيلة الدفع')">
                @if($gateways === [])
                    <x-ui.alert tone="warning" :title="__('لا وسيلة دفع متاحة')">
                        {{ __('تواصل مع إدارة المنصة لتفعيل وسيلة دفع.') }}
                    </x-ui.alert>
                @else
                    <div class="grid gap-2">
                        @foreach($gateways as $index => $gateway)
                            <label class="flex items-start gap-3 p-3 rounded-md border cursor-pointer transition-colors
                                          border-line-strong hover:bg-surface-sunken has-[:checked]:border-primary has-[:checked]:bg-primary-subtle">
                                <input type="radio" name="gateway" value="{{ $gateway->key() }}"
                                       @checked($index === 0)
                                       class="size-5 mt-0.5 shrink-0 accent-[var(--color-primary)]">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-sm">{{ $gateway->title() }}</span>
                                    @if($gateway->description())
                                        <span class="block text-xs text-muted mt-0.5">{{ $gateway->description() }}</span>
                                    @endif
                                    @if($gateway->key() === 'wallet' && $balance)
                                        <span class="block text-xs text-muted mt-0.5 font-mono">
                                            {{ __('رصيدك: :balance', ['balance' => $balance->format()]) }}
                                        </span>
                                    @endif
                                    @if($gateway->isTestMode())
                                        <x-ui.badge tone="warning" class="mt-1">{{ __('وضع تجريبي') }}</x-ui.badge>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('ملاحظات')">
                <x-ui.field :label="__('أي شيء نحتاج معرفته؟')" for="notes" class="mb-0">
                    <x-ui.textarea name="notes" id="notes" rows="3">{{ old('notes') }}</x-ui.textarea>
                </x-ui.field>
            </x-ui.card>
        </div>

        <div class="min-w-0 lg:sticky lg:top-20">
            <x-ui.card :title="__('طلبك')" :padding="false">
                <ul class="divide-y divide-[var(--color-line)]">
                    @foreach($cart->items as $item)
                        <li class="px-5 py-3 flex items-start justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate">{{ $item->product?->title }}</span>
                                @if($item->quantity > 1)
                                    <span class="text-2xs text-subtle font-mono">× {{ $item->quantity }}</span>
                                @endif
                            </span>
                            <span class="font-mono tabular shrink-0">{{ $item->total()->format() }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="p-5 border-t border-line">
                    <x-ui.description-list :items="array_filter([
                        __('المجموع') => $totals['subtotal']->format(),
                        $totals['discount']->isZero() ? null : __('الخصم') => $totals['discount']->isZero() ? null : '− '.$totals['discount']->format(),
                        $totals['shipping']->isZero() ? null : __('الشحن') => $totals['shipping']->isZero() ? null : $totals['shipping']->format(),
                        $totals['tax']->isZero() ? null : __('الضريبة') => $totals['tax']->isZero() ? null : $totals['tax']->format(),
                        __('الإجمالي') => $totals['total']->format(),
                    ])" />

                    <x-ui.button type="submit" class="w-full mt-4" :disabled="$gateways === []">
                        {{ $totals['total']->isZero() ? __('تأكيد') : __('ادفع :amount', ['amount' => $totals['total']->format()]) }}
                    </x-ui.button>

                    <p class="text-2xs text-subtle mt-3 text-center">
                        {{ __('بإتمام الشراء أنت توافق على الشروط وسياسة الاسترداد.') }}
                    </p>
                </div>
            </x-ui.card>
        </div>
    </form>
</main>

<x-site.footer />
</x-layouts.app>
