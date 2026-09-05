@php
    $typeLabels = [
        'physical' => 'منتج', 'digital' => 'رقمي', 'bundle' => 'باقة',
        'service' => 'خدمة', 'membership' => 'عضوية',
    ];
    $sorts = [
        'latest' => 'الأحدث', 'popular' => 'الأكثر مبيعاً',
        'cheapest' => 'الأرخص أولاً', 'dearest' => 'الأغلى أولاً',
    ];
@endphp

<x-layouts.app :title="__('المتجر')">
<x-site.header />

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('المتجر')" :subtitle="__('كتب ومذكّرات وأدوات — تُشترى وتُشحن أو تُنزَّل.')" />

    <form method="GET" action="{{ url('/shop') }}" class="flex flex-wrap gap-2 mb-6">
        <div class="min-w-[180px] flex-1">
            <label for="shop-q" class="sr-only">{{ __('ابحث في المتجر') }}</label>
            <x-ui.input id="shop-q" name="q" type="search" :value="request('q')"
                        :placeholder="__('ابحث في المتجر…')" />
        </div>

        <label for="shop-sort" class="sr-only">{{ __('الترتيب') }}</label>
        <select id="shop-sort" name="sort" onchange="this.form.submit()"
                class="min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
            @foreach($sorts as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>{{ __($label) }}</option>
            @endforeach
        </select>

        <x-ui.button type="submit" variant="secondary">{{ __('تصفية') }}</x-ui.button>
    </form>

    @if($products->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا منتجات معروضة')">
                {{ request('q')
                    ? __('لا نتائج لبحثك. جرّب كلمةً أخرى.')
                    : __('لم يُعرض شيء في المتجر بعد.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $product)
                <a href="{{ url('/shop/'.$product->slug) }}"
                   class="surface-card overflow-hidden flex flex-col hover:border-primary transition-colors group">

                    <div class="aspect-[4/3] bg-surface-sunken grid place-items-center overflow-hidden">
                        @if($product->cover_path)
                            <img src="{{ $product->cover_path }}" alt=""
                                 class="w-full h-full object-cover" loading="lazy">
                        @else
                            <span class="text-3xl text-subtle" aria-hidden="true">◪</span>
                        @endif
                    </div>

                    <div class="p-3 flex flex-col gap-2 flex-1">
                        <p class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors">
                            {{ $product->title }}
                        </p>

                        <div class="mt-auto flex flex-wrap items-baseline gap-2">
                            <span class="font-mono text-sm font-semibold tabular">{{ $product->price()->format() }}</span>

                            @if($product->isOnSale())
                                {{-- السعر القديم مشطوبٌ بجواره: الخصم يُرى بالفرق لا بكلمة --}}
                                <span class="font-mono text-2xs text-subtle line-through tabular">{{ $product->fullPrice()->format() }}</span>
                            @endif
                        </div>

                        @if($product->isOutOfStock())
                            <x-ui.badge tone="danger">{{ __('نفد المخزون') }}</x-ui.badge>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">{{ $products->links() }}</div>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
