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
    </div>

    <div class="grid gap-4">
        @foreach($attempt->answers as $answer)
            @php
                $frozen = $snapshot[$answer->question_id] ?? null;
                $body = $frozen['body'][app()->getLocale()] ?? $frozen['body']['ar'] ?? ($answer->question?->body ?? '—');
                $max = (float) ($frozen['marks'] ?? $answer->question?->marks ?? 0);
                $value = $answer->answer['value'] ?? null;
            @endphp

            <x-ui.card>
                <p class="font-semibold leading-relaxed mb-2">{{ $body }}</p>

                <div class="rounded-md bg-surface-sunken p-3 text-sm leading-relaxed whitespace-pre-line mb-3">
                    {{ is_array($value) ? implode(' · ', $value) : ($value ?: __('لم يُجب الطالب.')) }}
                </div>

                @if(filled($answer->question?->options))
                    <details class="mb-3">
                        <summary class="text-xs text-muted cursor-pointer py-1">{{ __('الإجابة النموذجية') }}</summary>
                        <p class="text-xs text-muted mt-1 font-mono">{{ implode(' · ', (array) $answer->question->correct) }}</p>
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
