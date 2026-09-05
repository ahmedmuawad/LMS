<x-layouts.student :title="__('مراجعتي')" current="review">

    <x-ui.page-header :title="__('مراجعتي')"
                      :subtitle="__('ما أخطأتَ فيه يعود إليك حتى تُتقنه — لا درجة هنا ولا يراها مدرّسك.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    <div class="grid gap-3 sm:grid-cols-2 mb-6">
        <x-ui.stat :label="__('بانتظار المراجعة')" :value="number_format($pending)" />
        <x-ui.stat :label="__('أتقنتَها')" :value="number_format($mastered)" />
    </div>

    @if($pending === 0)
        <x-ui.card>
            <x-ui.empty :title="$mastered > 0 ? __('لا شيء للمراجعة الآن') : __('لم تُخطئ في شيء بعد')">
                {{ $mastered > 0
                    ? __('أتقنتَ كل ما أخطأتَ فيه. وإن أخطأتَ في اختبارٍ قادم عاد السؤال إلى هنا.')
                    : __('يظهر هنا ما تُخطئ فيه في الاختبارات، فتعيده حتى تُتقنه.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-muted text-sm leading-relaxed min-w-0">
                    {{ __('السؤال الواحد بعد الآخر، مع الشرح فور الإجابة. وإجابتان صحيحتان متتاليتان تُخرجان السؤال من قائمتك.') }}
                </p>
                <x-ui.button :href="url('/my-review/next')">{{ __('ابدأ المراجعة') }}</x-ui.button>
            </div>
        </x-ui.card>

        @if($byCourse->count() > 1 || $byCourse->first()['course'] !== null)
            <x-ui.card :title="__('أين ضعفك')">
                <div class="grid gap-1.5">
                    @foreach($byCourse as $row)
                        <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                            <span class="min-w-0 flex-1 text-sm truncate">
                                {{ $row['course']?->title ?? __('أسئلة عامة') }}
                            </span>

                            <span class="font-mono text-xs tabular text-muted">
                                {{ trans_choice('سؤال|سؤالان|:count أسئلة|:count سؤالاً', $row['count'], ['count' => $row['count']]) }}
                            </span>

                            @if($row['course'] !== null)
                                <x-ui.button size="sm" variant="ghost"
                                             :href="url('/my-review/next?course='.$row['course']->getKey())">
                                    {{ __('راجعها') }}
                                </x-ui.button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif

</x-layouts.student>
