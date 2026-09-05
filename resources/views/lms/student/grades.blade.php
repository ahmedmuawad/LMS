<x-layouts.student :title="__('درجاتي')" current="grades">

    <x-ui.page-header :title="__('درجاتي')"
                      :subtitle="__('درجتك في كل اختبار وواجب، ومجموعك في كل كورس. وأفضل محاولة هي المحسوبة.')" />

    @if($skills->isNotEmpty())
        {{--
            المهارات قبل الدرجات: الدرجة تقول «كم»، والمهارة تقول
            «أين» — و«أين» هي ما يُعمَل به.
        --}}
        <x-ui.card :title="__('مهاراتك')" class="mb-6">
            <p class="text-2xs text-subtle mb-4">
                {{ __('تُقاس من إجاباتك في الاختبارات. والأضعف أوّلاً — لأن اللوحة تُقرأ لتُعرف الفجوة.') }}
            </p>

            <div class="grid gap-3">
                @foreach($skills as $row)
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <span class="text-sm min-w-0 truncate">{{ $row['skill']->name }}</span>

                            <span class="font-mono text-2xs tabular {{ $row['mastered'] ? 'text-success' : 'text-warning' }}">
                                {{ $row['percent'] }}%
                                <span class="text-subtle">· {{ $row['right'] }}/{{ $row['asked'] }}</span>
                            </span>
                        </div>

                        <div class="h-1.5 rounded-full bg-surface-sunken overflow-hidden">
                            <div class="h-full rounded-full {{ $row['mastered'] ? 'bg-success' : 'bg-warning' }}"
                                 style="width: {{ $row['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    @if($courses->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا درجات بعد')">
                {{ __('تظهر هنا درجاتك حين يكون في كورساتك اختبارٌ أو واجب وتُسلّمه.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-5">
            @foreach($courses as $row)
                <x-ui.card :title="$row['course']->title">
                    <x-slot:header>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold truncate">{{ $row['course']->title }}</h3>
                            <p class="text-xs text-subtle mt-0.5">
                                {{ __(':total من :max', [
                                    'total' => rtrim(rtrim(number_format($row['total'], 2), '0'), '.'),
                                    'max' => rtrim(rtrim(number_format($row['max'], 2), '0'), '.'),
                                ]) }}
                            </p>
                        </div>
                    </x-slot:header>

                    <x-slot:actions>
                        <x-ui.badge :tone="$row['percent'] >= 60 ? 'success' : ($row['percent'] > 0 ? 'warning' : 'neutral')">
                            {{ $row['percent'] }}%
                        </x-ui.badge>
                    </x-slot:actions>

                    <div class="grid gap-1.5">
                        @foreach($row['columns'] as $column)
                            @php $score = $row['cells'][$column['key']] ?? null; @endphp

                            <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                                <span class="shrink-0 text-subtle" aria-hidden="true">
                                    {{ $column['type'] === 'quiz' ? '◫' : '✎' }}
                                </span>

                                <span class="min-w-0 flex-1 text-sm truncate">{{ $column['title'] }}</span>

                                @if($score === null)
                                    {{-- «لم تُسلّم» أوضح من شرطة: الطالب يحتاج أن يعرف أن عليه عملاً --}}
                                    <x-ui.badge tone="neutral">{{ __('لم تُسلّم') }}</x-ui.badge>
                                @else
                                    <span class="font-mono text-xs tabular">
                                        {{ rtrim(rtrim(number_format((float) $score, 2), '0'), '.') }}
                                        <span class="text-subtle">/ {{ rtrim(rtrim(number_format($column['max'], 2), '0'), '.') }}</span>
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

</x-layouts.student>
