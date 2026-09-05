<x-layouts.student :title="__('مراجعتي')" current="review">

    <x-ui.page-header :title="__('مراجعتي')" :back="url('/my-review')">
        <x-slot:actions>
            <span class="font-mono text-xs tabular text-subtle">
                {{ __('بقي :count', ['count' => number_format($remaining)]) }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="max-w-[720px]">
        <x-ui.card>
            <div class="flex items-start justify-between gap-3 mb-4">
                <p class="font-semibold leading-relaxed min-w-0">
                    <x-ui.math inline>{{ $question->body }}</x-ui.math>
                </p>

                {{-- عدّاد الخطأ يُقال: من أخطأ ثلاث مرّات يعرف أن هذا موضعُ ضعفه --}}
                @if((int) $item->wrong_count > 1)
                    <x-ui.badge tone="warning" class="shrink-0">
                        {{ __('أخطأتَ فيه :count مرّات', ['count' => $item->wrong_count]) }}
                    </x-ui.badge>
                @endif
            </div>

            <form method="POST" action="{{ url('/my-review/'.$item->getKey().'/answer') }}" class="grid gap-4">
                @csrf
                <input type="hidden" name="course" value="{{ $course }}">

                <x-lms.question-input :type="$question->type" name="answer"
                                      :options="$question->options ?? []" />

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit">{{ __('تحقّق') }}</x-ui.button>
                    <x-ui.button variant="ghost" :href="url('/my-review')">{{ __('أوقف المراجعة') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

</x-layouts.student>
