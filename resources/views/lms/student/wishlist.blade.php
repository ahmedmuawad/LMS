@php
    use App\Core\Support\Money;

    // مسار العنصر يختلف بنوعه — والبطاقة واحدة
    $urlFor = fn (string $type, $item): string => match ($type) {
        'course' => url('/courses/'.$item->slug),
        'service' => url('/services/'.$item->slug),
        default => url('/shop/'.($item->slug ?? $item->getKey())),
    };
@endphp

<x-layouts.student :title="__('قائمة الأمنيات')" current="wishlist">

    <x-ui.page-header :title="__('قائمة الأمنيات')"
                      :subtitle="__('ما أجّلت شراءه — ونُنبّهك إن نزل سعره عمّا رأيته.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    @if($items->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('قائمتك فارغة')">
                {{ __('اضغط ♡ على أي كورس أو خدمة أو منتج ليظهر هنا.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($items as $entry)
                @php
                    $row = $entry['row'];
                    $item = $entry['item'];
                    $now = $item->price_minor ?? null;
                    $then = $row->price_minor_at_add;
                    $dropped = $then !== null && $now !== null && $now < $then;
                @endphp

                <div class="surface-card p-4 grid gap-3">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <x-ui.badge tone="neutral" class="mb-1.5">{{ $row->typeLabel() }}</x-ui.badge>
                            <a href="{{ $urlFor($row->itemable_type, $item) }}"
                               class="block font-semibold text-sm hover:text-primary transition-colors">
                                {{ $item->title ?? $item->name ?? __('عنصر') }}
                            </a>
                        </div>

                        <form method="POST" action="{{ route('wishlist.toggle') }}" class="shrink-0">
                            @csrf
                            <input type="hidden" name="type" value="{{ $row->itemable_type }}">
                            <input type="hidden" name="id" value="{{ $row->itemable_id }}">
                            <button type="submit"
                                    class="min-w-11 min-h-11 -m-2 grid place-items-center text-danger hover:brightness-110"
                                    aria-label="{{ __('إزالة من القائمة') }}">♥</button>
                        </form>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @if($now !== null)
                            <span class="font-mono text-sm font-semibold tabular">
                                {{ Money::fromMinor((int) $now, (string) ($item->currency ?? $row->currency ?? 'EGP'))->format() }}
                            </span>
                        @endif

                        @if($dropped)
                            {{-- الفرق هو سبب وجود القائمة: يُقال بالرقم لا بكلمة «تخفيض» --}}
                            <span class="text-2xs text-success font-semibold">
                                {{ __('نزل :amount عمّا رأيته', [
                                    'amount' => Money::fromMinor((int) ($then - $now), (string) ($item->currency ?? $row->currency ?? 'EGP'))->format(),
                                ]) }}
                            </span>
                        @endif
                    </div>

                    <div>
                        <x-ui.button size="sm" variant="secondary"
                                     :href="$urlFor($row->itemable_type, $item)">{{ __('عرض') }}</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-layouts.student>
