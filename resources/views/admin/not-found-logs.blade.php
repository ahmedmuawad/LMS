<x-layouts.admin :title="__('الروابط المكسورة')" current="not-found">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('الروابط المكسورة')"
                      :subtitle="__('كل عنوانٍ طُلب فلم يوجد — والأكثر طلباً أولاً، لأنه يُخسّر أكثر الزوّار.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا روابط مكسورة')">
                {{ $resolved > 0
                    ? __('أصلحتَ :count رابطاً. ويظهر هنا كل عنوانٍ جديد يُطلَب فلا يوجد.', ['count' => $resolved])
                    : __('يظهر هنا كل عنوانٍ يُطلَب فلا يوجد — من جوجل أو من منشورٍ قديم — لتُحوّله إلى مكانه الجديد.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="divide-y divide-[var(--color-line)]">
                @foreach($rows as $row)
                    <div class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="font-mono text-sm break-all" dir="ltr">/{{ ltrim($row->path, '/') }}</p>

                                @if($row->referrer)
                                    <p class="text-2xs text-subtle mt-1 truncate" dir="ltr">
                                        {{ __('من:') }} {{ $row->referrer }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <x-ui.badge :tone="$row->hits >= 10 ? 'danger' : 'neutral'">
                                    {{ __(':n طلباً', ['n' => number_format($row->hits)]) }}
                                </x-ui.badge>

                                <span class="text-2xs text-subtle font-mono tabular">
                                    {{ $row->last_seen_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        {{-- الإصلاح في السطر نفسه: شاشةٌ ثانية تعني أن يُنسخ المسار ويُلصق --}}
                        <form method="POST" action="{{ url('/admin/not-found/'.$row->id.'/resolve') }}"
                              class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                            @csrf

                            <label class="sr-only" for="to-{{ $row->id }}">{{ __('حوّله إلى') }}</label>
                            <x-ui.input :id="'to-'.$row->id" name="to" dir="ltr" required
                                        :placeholder="__('/courses/الرابط-الجديد')" />

                            <x-ui.button type="submit" size="sm">{{ __('حوّله ٣٠١') }}</x-ui.button>
                        </form>

                        <form method="POST" action="{{ url('/admin/not-found/'.$row->id.'/dismiss') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="text-2xs text-subtle hover:text-content transition-colors">
                                {{ __('أخفِ هذا السطر') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <div class="mt-5">{{ $rows->links() }}</div>
    @endif

</div>
</x-layouts.admin>
