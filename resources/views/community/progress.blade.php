<x-layouts.student :title="__('تقدّمي')" current="progress">

<div>

    <x-ui.page-header :title="__('تقدّمي')" :subtitle="__('نقاطك وشاراتك وأيامك المتتابعة.')">
        <x-slot:actions>
            @if(setting('gamification.leaderboard', true))
                <x-ui.button as="a" :href="url('/leaderboard')" variant="secondary">{{ __('لوحة الصدارة') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('النقاط')" :value="number_format($summary['points'])" />
        <x-ui.stat :label="__('المستوى')" :value="$summary['level']" />
        <x-ui.stat :label="__('أيام متتابعة')" :value="$summary['streak']" />
        <x-ui.stat :label="__('أطول تتابع')" :value="$summary['longest']" />
    </div>

    @if($summary['to_next'] > 0)
        @php
            $next = $summary['level'] + 1;
            $floor = 50 * (($summary['level'] - 1) ** 2);
            $ceiling = 50 * (($next - 1) ** 2);
            $span = max(1, $ceiling - $floor);
            $done = max(0, min(100, (int) round(($summary['points'] - $floor) / $span * 100)));
        @endphp
        <div class="surface-card p-4 mb-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                <p class="font-semibold text-sm">{{ __('إلى المستوى :level', ['level' => $next]) }}</p>
                <p class="text-2xs text-subtle font-mono tabular">
                    {{ trans_choice('{1} نقطة واحدة|{2} نقطتان|[3,10] :count نقاط|[11,*] :count نقطة', $summary['to_next'], ['count' => $summary['to_next']]) }}
                </p>
            </div>
            <x-ui.progress :value="$done" />
        </div>
    @endif

    <section class="mb-8">
        <h2 class="text-lg font-bold mb-3">{{ __('الشارات') }}</h2>

        @php $ownedKeys = $summary['badges']->pluck('key')->all(); @endphp

        <ul class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($allBadges as $badge)
                @php $owned = in_array($badge->key, $ownedKeys, true); @endphp
                <li @class(['surface-card p-4 text-center', 'opacity-50' => ! $owned])>
                    <span @class(['size-12 rounded-xl grid place-items-center mx-auto mb-2 text-xl',
                                  'bg-'.$badge->tone.'-subtle text-'.$badge->tone => $owned,
                                  'bg-surface-sunken text-subtle' => ! $owned]) aria-hidden="true">{{ $badge->icon }}</span>
                    <p class="font-semibold text-sm leading-snug">{{ $badge->name }}</p>
                    <p class="text-2xs text-subtle mt-1 leading-relaxed">{{ $badge->description }}</p>
                    @unless($owned)
                        <p class="text-2xs text-subtle mt-1.5">{{ __('لم تُنَل بعد') }}</p>
                    @endunless
                </li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2 class="text-lg font-bold mb-3">{{ __('سجلّ النقاط') }}</h2>

        @if($entries->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا نقاط بعد')">{{ __('أتمّ درساً واحداً لتبدأ.') }}</x-ui.empty>
            </x-ui.card>
        @else
            <ul class="surface-card divide-y divide-[var(--color-line)]">
                @foreach($entries as $entry)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium truncate">{{ __($rules[$entry->rule]['label'] ?? $entry->rule) }}</p>
                            <p class="text-2xs text-subtle mt-0.5">{{ $entry->note ?: $entry->created_at?->diffForHumans() }}</p>
                        </div>
                        <span @class(['font-mono font-bold tabular shrink-0',
                                      'text-success' => $entry->points > 0,
                                      'text-danger' => $entry->points < 0])>{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</span>
                    </li>
                @endforeach
            </ul>

            @if($entries->hasPages())
                <div class="mt-6">
                    <x-ui.pagination :current="$entries->currentPage()" :last="$entries->lastPage()"
                                     :url="request()->fullUrlWithQuery(['page' => '']).''" />
                </div>
            @endif
        @endif
    </section>
</div>

</x-layouts.student>
