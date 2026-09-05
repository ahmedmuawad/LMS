<x-layouts.app :title="$product->title">
<x-site.header />

<main id="main" class="max-w-[1100px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.breadcrumb :items="[
        ['label' => __('المتجر'), 'url' => url('/shop')],
        ['label' => $product->title],
    ]" class="mb-5" />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] items-start">

        <div class="min-w-0">
            <div class="rounded-lg overflow-hidden border border-line bg-surface-sunken aspect-[4/3] grid place-items-center mb-5">
                @if($product->cover_path)
                    <img src="{{ $product->cover_path }}" alt="{{ $product->title }}"
                         class="w-full h-full object-cover">
                @else
                    <span class="text-5xl text-subtle" aria-hidden="true">◪</span>
                @endif
            </div>

            <h1 class="text-xl sm:text-2xl font-extrabold mb-3">{{ $product->title }}</h1>

            @if($product->short_description)
                <p class="text-sm text-muted leading-relaxed mb-5">{{ $product->short_description }}</p>
            @endif

            @if($product->description)
                {{--
                    الوصف يُهرَب كبقيّة المشروع.
                    طباعتُه خاماً تجعل موظّفاً بصلاحية المنتجات يزرع
                    جافاسكربت يعمل عند كل زائر — وصفحة الكورس تُهرّبه
                    منذ البداية، فلا معنى لأن يفترق المتجر عنها.
                --}}
                <div class="text-sm leading-loose text-muted whitespace-pre-line">{{ $product->description }}</div>
            @endif
        </div>

        {{--
            بطاقة الشراء لاصقة على الشاشة الواسعة: الوصف قد يطول
            صفحات، وزرّ الشراء الذي يمضي مع التمرير يُنسى.
        --}}
        <aside class="lg:sticky lg:top-20 surface-card p-5 grid gap-4">

            <div class="flex flex-wrap items-baseline gap-2">
                <span class="font-mono text-2xl font-bold tabular">{{ $product->price()->format() }}</span>
                @if($product->isOnSale())
                    <span class="font-mono text-sm text-subtle line-through tabular">{{ $product->fullPrice()->format() }}</span>
                @endif
            </div>

            @if($product->sku)
                <p class="text-2xs text-subtle font-mono">{{ __('الرمز') }}: {{ $product->sku }}</p>
            @endif

            @if($product->isOutOfStock())
                <x-ui.alert tone="warning">
                    {{ __('نفد المخزون. تابعنا — سنُعيد توفيره قريباً.') }}
                </x-ui.alert>
            @else
                @if($product->manage_stock && $product->stock_qty <= 5)
                    {{-- الندرة تُقال بالرقم لا بكلمة «سارع» --}}
                    <p class="text-xs text-warning font-semibold">
                        {{ __('بقي :n فقط', ['n' => $product->stock_qty]) }}
                    </p>
                @endif

                <form method="POST" action="{{ route('cart.add') }}" class="grid gap-3">
                    @csrf
                    <input type="hidden" name="type" value="product">
                    <input type="hidden" name="id" value="{{ $product->getKey() }}">

                    <x-ui.field :label="__('الكمية')" for="qty" class="mb-0">
                        <x-ui.input id="qty" name="quantity" type="number" value="1" min="1"
                                    max="{{ $product->manage_stock && ! $product->allow_backorder ? $product->stock_qty : 99 }}" />
                    </x-ui.field>

                    <x-ui.button type="submit" size="lg" class="w-full">{{ __('أضف إلى السلة') }}</x-ui.button>
                </form>
            @endif

            @if($product->requires_shipping)
                <p class="text-2xs text-subtle leading-relaxed">{{ __('يُشحن إلى عنوانك — تُحسب مصاريف الشحن عند الدفع.') }}</p>
            @endif
        </aside>
    </div>

    @if($related->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-line">
            <h2 class="text-base font-bold mb-4">{{ __('منتجات مشابهة') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($related as $item)
                    <a href="{{ url('/shop/'.$item->slug) }}"
                       class="surface-card p-3 hover:border-primary transition-colors group">
                        <p class="text-sm font-semibold line-clamp-2 group-hover:text-primary transition-colors">{{ $item->title }}</p>
                        <p class="font-mono text-xs tabular text-muted mt-1.5">{{ $item->price()->format() }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
