<x-layouts.app :title="__('لوحة الصدارة')">
<x-site.header />

<main id="main" class="max-w-[760px] mx-auto px-4 sm:px-6 py-8">

    @php
        $period = (string) setting('gamification.leaderboard_period', 'week');
        $periodLabel = __(['week' => 'هذا الأسبوع', 'month' => 'هذا الشهر', 'all' => 'منذ البداية'][$period] ?? '');
        $anonymous = (bool) setting('gamification.leaderboard_anonymous', true);
    @endphp

    <x-ui.page-header :title="__('لوحة الصدارة')" :subtitle="$periodLabel" :back="url('/my-progress')" />

    {{-- ترتيبك أنت أولاً: من في القاع يحتاج أن يرى تقدّمه لا أن يُخفى عنه --}}
    <div class="surface-card p-4 mb-5 flex flex-wrap items-center gap-4">
        <span class="size-12 rounded-xl grid place-items-center bg-primary-subtle text-primary font-mono font-bold shrink-0">
            #{{ $myRank }}
        </span>
        <div class="flex-1 min-w-0">
            <p class="font-semibold">{{ __('ترتيبك') }}</p>
            <p class="text-2xs text-subtle mt-0.5">
                {{ trans_choice('{0} لا نقاط بعد|{1} نقطة واحدة|{2} نقطتان|[3,10] :count نقاط|[11,*] :count نقطة', $myPoints, ['count' => $myPoints]) }}
                @if($streak->isStreakAlive() && $streak->current_days > 1)
                    · {{ trans_choice('{2} يومان متتابعان|[3,10] :count أيام متتابعة|[11,*] :count يوماً متتابعاً', (int) $streak->current_days, ['count' => (int) $streak->current_days]) }}
                @endif
            </p>
        </div>
    </div>

    @if($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا نقاط بعد في هذه المدة')">{{ __('كن أول من يبدأ.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <ol class="surface-card divide-y divide-[var(--color-line)]">
            @foreach($rows as $index => $row)
                @php
                    $user = $users->get($row->user_id);
                    $isMe = (int) $row->user_id === (int) auth()->id();
                    $show = ! $anonymous || $index < 10 || $isMe;
                    $medal = [0 => '🥇', 1 => '🥈', 2 => '🥉'][$index] ?? null;
                @endphp
                <li @class(['flex items-center gap-3 px-4 py-3', 'bg-primary-subtle' => $isMe])>
                    <span class="w-9 shrink-0 text-center font-mono font-bold tabular">
                        {{ $medal ?? '#'.($index + 1) }}
                    </span>

                    <x-ui.avatar :name="$show ? ($user?->name ?? '') : '؟'" size="sm" />

                    <span class="flex-1 min-w-0 truncate font-medium text-sm">
                        {{ $show ? ($user?->name ?? __('مستخدم')) : __('متعلّم') }}
                        @if($isMe)<span class="text-2xs text-primary">({{ __('أنت') }})</span>@endif
                    </span>

                    <span class="font-mono font-bold tabular shrink-0">{{ number_format((int) $row->total) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
