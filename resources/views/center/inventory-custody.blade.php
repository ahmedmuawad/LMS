<x-layouts.admin :title="__('العُهد والنواقص')" current="inventory-custody">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('العُهد والنواقص')"
                      :subtitle="__('ما هو عند الناس ولم يعد، وما قارب أن ينفد من المخزن.')"
                      :back="url('/admin/inventory')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($low->isNotEmpty())
        <x-ui.card :title="__('أوشك أن ينفد')" class="mb-6">
            <div class="grid gap-1.5">
                @foreach($low as $item)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <span class="min-w-0 flex-1 text-sm truncate">{{ $item->name }}</span>

                        <span class="font-mono text-xs tabular text-danger">
                            {{ __('بقي :n', ['n' => (int) $item->stock_qty]) }}
                        </span>

                        <span class="text-2xs text-subtle font-mono tabular">
                            {{ __('حدّ التنبيه :n', ['n' => (int) $item->reorder_level]) }}
                        </span>

                        <x-ui.button size="sm" variant="ghost"
                                     :href="url('/admin/inventory/'.$item->getKey().'/movements')">
                            {{ __('ورّد') }}
                        </x-ui.button>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <x-ui.card :title="__('عُهد لم تُردّ')">
        @if($open->isEmpty())
            <x-ui.empty :title="__('لا عُهد مفتوحة')">
                {{ __('كل ما سُلّم عاد. ويظهر هنا ما تسلّمه لمدرّسيك أو طلبتك وتنتظر ردّه.') }}
            </x-ui.empty>
        @else
            <div class="grid gap-1.5">
                @foreach($open as $movement)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <span class="min-w-0 flex-1 text-sm truncate">
                            {{ $movement->item?->name ?? '—' }}
                            <span class="text-subtle">· {{ $movement->holder() }}</span>
                        </span>

                        <span class="font-mono text-xs tabular">{{ abs((int) $movement->qty) }}</span>

                        <span class="text-2xs text-subtle font-mono tabular">
                            {{ __('منذ :when', ['when' => $movement->created_at?->diffForHumans(null, true)]) }}
                        </span>

                        <form method="POST" action="{{ url('/admin/inventory/movements/'.$movement->id.'/return') }}">
                            @csrf
                            <x-ui.button size="sm" variant="ghost" type="submit">{{ __('رُدّت') }}</x-ui.button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $open->links() }}</div>
        @endif
    </x-ui.card>

</div>
</x-layouts.admin>
