<x-layouts.student :title="__('شاراتي')" current="badges">

    <x-ui.page-header :title="__('شاراتي')"
                      :subtitle="__(':earned من :total — والباقي معروضٌ لتعرف كيف تناله.', ['earned' => $badges->whereNotNull('awarded_at')->count(), 'total' => $badges->count()])" />

    @if($badges->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا شارات في هذه المنصة')">
                {{ __('لم يُعرّف مدرّسك شارات بعد.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        {{--
            المنالة أولاً ثم الباقي: ما أنجزتَه يُرى قبل ما ينقصك.
            وترتيبٌ يبدأ بالنقص يقرأ كقائمة إخفاقات.
        --}}
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($badges->sortByDesc(fn (array $e): int => $e['awarded_at'] !== null ? 1 : 0) as $entry)
                @php $badge = $entry['badge']; $earned = $entry['awarded_at'] !== null; @endphp

                <div @class([
                    'surface-card p-4 text-center grid gap-2 justify-items-center',
                    'opacity-45' => ! $earned,
                ])>
                    <span @class([
                        'w-12 h-12 rounded-full grid place-items-center text-xl shrink-0',
                        'bg-primary-subtle text-primary' => $earned,
                        'bg-surface-sunken text-subtle' => ! $earned,
                    ]) aria-hidden="true">{{ $badge->icon ?: '★' }}</span>

                    <p class="text-xs font-semibold leading-snug">{{ $badge->name }}</p>

                    @if($badge->description)
                        <p class="text-2xs text-muted leading-relaxed">{{ $badge->description }}</p>
                    @endif

                    @if($earned)
                        <p class="text-2xs text-success font-mono tabular">
                            {{ \Illuminate\Support\Carbon::parse($entry['awarded_at'])->translatedFormat('j M Y') }}
                        </p>
                    @elseif($badge->points)
                        <p class="text-2xs text-subtle font-mono tabular">{{ __(':points نقطة', ['points' => $badge->points]) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</x-layouts.student>
