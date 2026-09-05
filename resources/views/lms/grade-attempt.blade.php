@php
    $snapshot = collect($attempt->snapshot ?? [])->keyBy('id');
@endphp

<x-layouts.admin :title="__('تصحيح محاولة')" current="grading">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('تصحيح محاولة')"
                      :subtitle="$attempt->enrollment?->user?->name.' · '.$attempt->quiz?->title"
                      :back="url('/admin/grading')" />

    <div class="grid gap-4 sm:grid-cols-3 mb-4">
        <x-ui.stat :label="__('الدرجة الحالية')"
                   :value="rtrim(rtrim(number_format($attempt->score, 2), '0'), '.').' / '.rtrim(rtrim(number_format($attempt->max_score, 2), '0'), '.')" />
        <x-ui.stat :label="__('النسبة')" :value="rtrim(rtrim(number_format($attempt->percentage, 2), '0'), '.').'%'" />
        <x-ui.stat :label="__('زمن الحل')" :value="gmdate('i:s', (int) $attempt->time_spent_seconds)" />

        @if($attempt->quiz?->proctored)
            <x-ui.stat :label="__('مخالفات المراقبة')" :value="(int) $attempt->violations" />
        @endif
    </div>

    <div class="grid gap-4">
        @if($events->isNotEmpty())
            {{--
                السجلّ قبل الإجابات: يقرؤه المصحّح ثم ينظر بعينٍ تعرف
                ما وقع، لا بعد أن يكون قد قرّر.
            --}}
            <x-ui.card :title="__('سجلّ المراقبة')" class="mb-4">
                @if($attempt->auto_submitted)
                    <x-ui.alert tone="warning" class="mb-3">
                        {{ __('سُلّمت هذه الورقة تلقائياً لبلوغ حدّ المخالفات.') }}
                    </x-ui.alert>
                @endif

                <p class="text-xs text-muted leading-relaxed mb-3">
                    {{ __('ما يقع خارج الجهاز لا يُرصد — لا كتابٌ بجواره ولا هاتفٌ ثانٍ. وهذا ما وقع داخله.') }}
                </p>

                <ul class="grid gap-1.5">
                    @foreach($events as $event)
                        <li class="flex items-center gap-3 text-xs">
                            <span class="font-mono tabular text-subtle shrink-0">{{ $event->atLabel() }}</span>
                            <span class="text-muted">{{ $event->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @foreach($attempt->answers as $answer)
            @php
                $frozen = $snapshot[$answer->question_id] ?? null;
                $body = $frozen['body'][app()->getLocale()] ?? $frozen['body']['ar'] ?? ($answer->question?->body ?? '—');
                $max = (float) ($frozen['marks'] ?? $answer->question?->marks ?? 0);
                $value = $answer->answer['value'] ?? null;
            @endphp

            <x-ui.card>
                {{-- المدرّس يصحّح ما رآه الطالب: معادلةً معروضة لا صياغةَ TeX خاماً --}}
                <x-ui.math class="font-semibold mb-2">{{ $body }}</x-ui.math>

                <x-ui.math class="rounded-md bg-surface-sunken p-3 text-sm mb-3">{{ is_array($value) ? implode(' · ', $value) : ($value ?: __('لم يُجب الطالب.')) }}</x-ui.math>

                @if(filled($answer->question?->options))
                    <details class="mb-3">
                        <summary class="text-xs text-muted cursor-pointer min-h-11 flex items-center">{{ __('الإجابة النموذجية وخطواتها') }}</summary>
                        <div class="mt-2 grid gap-2">
                            <p class="text-xs text-muted">
                                @foreach((array) $answer->question->correct as $key)
                                    <x-ui.math inline class="me-2">{{ $answer->question->options[$key] ?? $key }}</x-ui.math>
                                @endforeach
                            </p>
                            @php $steps = $frozen['steps'][app()->getLocale()] ?? $frozen['steps']['ar'] ?? $answer->question?->getTranslation('steps', 'ar'); @endphp
                            @if(filled($steps))
                                <x-ui.solution-steps :steps="$steps" />
                            @endif
                        </div>
                    </details>
                @endif

                @if($answer->is_correct === null)
                    <form method="POST" action="{{ url('/admin/grading/attempts/'.$attempt->id.'/answers/'.$answer->id) }}"
                          class="grid gap-3 sm:grid-cols-[120px_minmax(0,1fr)_auto] sm:items-end">
                        @csrf @method('PUT')
                        <x-ui.field :label="__('الدرجة')" :for="'m'.$answer->id" class="mb-0"
                                    :hint="__('من :max', ['max' => rtrim(rtrim(number_format($max, 2), '0'), '.')])"
                                    :error="$errors->first('marks')">
                            <x-ui.input type="number" step="0.25" min="0" max="{{ $max }}"
                                        name="marks" :id="'m'.$answer->id" class="font-mono" />
                        </x-ui.field>
                        <x-ui.field :label="__('ملاحظة للطالب')" :for="'n'.$answer->id" class="mb-0">
                            <x-ui.input name="note" :id="'n'.$answer->id" :placeholder="__('ما الذي ينقص الإجابة؟')" />
                        </x-ui.field>
                        <x-ui.button type="submit" class="h-11">{{ __('حفظ') }}</x-ui.button>
                    </form>
                @else
                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-line">
                        <x-ui.badge :tone="$answer->is_correct ? 'success' : 'danger'">
                            {{ $answer->is_correct ? __('صحيحة') : __('خاطئة') }}
                        </x-ui.badge>
                        <span class="font-mono text-xs tabular text-muted">
                            {{ rtrim(rtrim(number_format((float) $answer->marks_awarded, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($max, 2), '0'), '.') }}
                        </span>
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>
</x-layouts.admin>
