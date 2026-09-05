<x-layouts.student :title="__('مراجعتي')" current="review">

    <x-ui.page-header :title="__('مراجعتي')" :back="url('/my-review')" />

    <div class="max-w-[720px] grid gap-4">

        <x-ui.alert :tone="$correct === true ? 'success' : ($correct === null ? 'info' : 'danger')">
            @if($correct === true)
                {{ $mastered
                    ? __('صحيحة — وأتقنتَ هذا السؤال، خرج من قائمتك.')
                    : __('صحيحة. صحيحةٌ أخرى في المرّة القادمة تُخرجه من قائمتك.') }}
            @elseif($correct === null)
                {{ __('هذا السؤال يصحّحه مدرّسك، فلا يُحكَم عليه هنا.') }}
            @else
                {{ __('ليست صحيحة. اقرأ الشرح ثم أعِده لاحقاً.') }}
            @endif
        </x-ui.alert>

        <x-ui.card>
            <p class="font-semibold leading-relaxed mb-4">
                <x-ui.math inline>{{ $question->body }}</x-ui.math>
            </p>

            {{-- الإجابة الصحيحة مُوسَّمة: من أخطأ يحتاج أن يرى الصواب لا أن يُقال له «خطأ» --}}
            <x-lms.question-input :type="$question->type" name="answer"
                                  :options="$question->options ?? []"
                                  :disabled="true"
                                  :correct="$question->correct ?? []" />

            @php
                $steps = $question->steps;
                $why = $question->explanation;
            @endphp

            @if(filled($steps))
                <div class="mt-4"><x-ui.solution-steps :steps="$steps" /></div>
            @endif

            @if(filled($why))
                <x-ui.math class="text-sm text-muted mt-4">{{ $why }}</x-ui.math>
            @endif

            @if(blank($steps) && blank($why))
                <p class="text-xs text-muted mt-4">{{ __('لم يضع مدرّسك شرحاً لهذا السؤال — راجع درسه.') }}</p>
            @endif
        </x-ui.card>

        <div class="flex flex-wrap gap-2">
            @if($remaining > 0)
                <x-ui.button :href="url('/my-review/next'.($course ? '?course='.$course : ''))">
                    {{ __('السؤال التالي') }} · <span class="font-mono">{{ number_format($remaining) }}</span>
                </x-ui.button>
            @endif

            <x-ui.button variant="ghost" :href="url('/my-review')">{{ __('عُد إلى مراجعتي') }}</x-ui.button>
        </div>

    </div>

</x-layouts.student>
