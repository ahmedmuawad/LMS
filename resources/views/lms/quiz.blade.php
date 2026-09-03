@php
    use App\Modules\Lms\Models\Question;
    $open = $attempt->isOpen();
    $showAnswers = ! $open && ($quiz->show_answers === 'after_submit'
        || ($quiz->show_answers === 'after_pass' && $attempt->passed));
    $answers = $attempt->answers()->get()->keyBy('question_id');
@endphp

<x-layouts.app :title="$quiz->title">
<div class="min-h-screen">
    <header class="sticky top-0 z-30 bg-surface/95 backdrop-blur border-b border-line px-4 sm:px-6 py-3 flex items-center gap-3">
        <a href="{{ url('/learn/'.$course->slug.'/'.$item->getKey()) }}"
           class="size-9 grid place-items-center rounded-md text-muted hover:bg-surface-sunken shrink-0"
           aria-label="{{ __('العودة') }}"><span class="flip-rtl" aria-hidden="true">←</span></a>

        <div class="min-w-0 flex-1">
            <p class="text-2xs text-subtle truncate">{{ $course->title }}</p>
            <p class="font-semibold text-sm truncate">{{ $quiz->title }}</p>
        </div>

        @if($open && $attempt->secondsLeft() !== null)
            <span class="font-mono text-sm tabular px-2.5 py-1 rounded-md bg-warning-subtle text-warning shrink-0"
                  x-data="{ left: {{ $attempt->secondsLeft() }} }"
                  x-init="setInterval(() => { if (left > 0) { left--; if (left === 0) $refs.form?.submit(); } }, 1000)"
                  x-text="String(Math.floor(left/60)).padStart(2,'0') + ':' + String(left%60).padStart(2,'0')"
                  role="timer" aria-live="off">--:--</span>
        @endif
    </header>

    <main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-6">
        @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

        @unless($open)
            <x-ui.card class="mb-4">
                <div class="grid gap-4 sm:grid-cols-3 text-center">
                    <div>
                        <p class="text-xs text-subtle mb-1">{{ __('درجتك') }}</p>
                        <p class="font-mono text-2xl tabular">{{ rtrim(rtrim(number_format($attempt->score, 2), '0'), '.') }}<span class="text-subtle text-base"> / {{ rtrim(rtrim(number_format($attempt->max_score, 2), '0'), '.') }}</span></p>
                    </div>
                    <div>
                        <p class="text-xs text-subtle mb-1">{{ __('النسبة') }}</p>
                        <p class="font-mono text-2xl tabular">{{ rtrim(rtrim(number_format($attempt->percentage, 2), '0'), '.') }}%</p>
                    </div>
                    <div>
                        <p class="text-xs text-subtle mb-1">{{ __('النتيجة') }}</p>
                        @if($attempt->status === 'submitted' && $attempt->awaitsGrading())
                            <x-ui.badge tone="info">{{ __('بانتظار تصحيح المدرّس') }}</x-ui.badge>
                        @elseif($attempt->passed)
                            <x-ui.badge tone="success">{{ __('ناجح') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="danger">{{ __('لم تبلغ نسبة النجاح') }}</x-ui.badge>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        @endunless

        <form method="POST" x-ref="form"
              action="{{ url('/learn/'.$course->slug.'/quiz/'.$item->getKey().'/attempt/'.$attempt->getKey()) }}">
            @csrf

            <div class="grid gap-4">
                @foreach($attempt->snapshot ?? [] as $index => $q)
                    @php
                        $body = $q['body'][app()->getLocale()] ?? $q['body']['ar'] ?? reset($q['body']);
                        $given = $answers[$q['id']] ?? null;
                        $name = 'answers['.$q['id'].']';
                    @endphp

                    <x-ui.card>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <p class="font-semibold leading-relaxed min-w-0">
                                <span class="text-subtle font-mono text-xs me-1">{{ $index + 1 }}.</span>{{ $body }}
                            </p>
                            <span class="text-2xs text-subtle font-mono shrink-0">{{ rtrim(rtrim(number_format((float) $q['marks'], 2), '0'), '.') }}</span>
                        </div>

                        @if($q['type'] === 'true_false')
                            <div class="grid gap-2">
                                @foreach(['1' => __('صح'), '0' => __('خطأ')] as $key => $label)
                                    <x-ui.checkbox type="radio" :name="$name" :value="$key" :label="$label"
                                                   :disabled="! $open"
                                                   :checked="(string) ($given?->answer['value'] ?? '') === (string) $key" />
                                @endforeach
                            </div>
                        @elseif(in_array($q['type'], ['single_choice', 'dropdown'], true))
                            <div class="grid gap-2">
                                @foreach(($q['options'] ?? []) as $key => $label)
                                    <x-ui.checkbox type="radio" :name="$name" :value="$key" :label="$label"
                                                   :disabled="! $open"
                                                   :checked="(string) ($given?->answer['value'] ?? '') === (string) $key" />
                                @endforeach
                            </div>
                        @elseif($q['type'] === 'multiple_choice')
                            <div class="grid gap-2">
                                @foreach(($q['options'] ?? []) as $key => $label)
                                    <x-ui.checkbox :name="$name.'[]'" :value="$key" :label="$label"
                                                   :disabled="! $open"
                                                   :checked="in_array((string) $key, array_map('strval', (array) ($given?->answer['value'] ?? [])), true)" />
                                @endforeach
                            </div>
                        @elseif($q['type'] === 'essay')
                            <x-ui.textarea :name="$name" rows="6" :disabled="! $open"
                                           :placeholder="__('اكتب إجابتك…')">{{ $given?->answer['value'] ?? '' }}</x-ui.textarea>
                        @else
                            <x-ui.input :name="$name" :disabled="! $open"
                                        value="{{ is_array($given?->answer['value'] ?? null) ? '' : ($given?->answer['value'] ?? '') }}"
                                        :placeholder="__('إجابتك…')" />
                        @endif

                        @unless($open)
                            <div class="mt-3 pt-3 border-t border-line flex items-center justify-between gap-3">
                                @if($given?->is_correct === null)
                                    <x-ui.badge tone="info">{{ __('بانتظار تصحيح المدرّس') }}</x-ui.badge>
                                @elseif($given->is_correct)
                                    <x-ui.badge tone="success">{{ __('إجابة صحيحة') }}</x-ui.badge>
                                @else
                                    <x-ui.badge tone="danger">{{ __('إجابة خاطئة') }}</x-ui.badge>
                                @endif
                                <span class="font-mono text-xs tabular text-muted">
                                    {{ rtrim(rtrim(number_format((float) ($given?->marks_awarded ?? 0), 2), '0'), '.') }}
                                </span>
                            </div>

                            @if($showAnswers && $given?->is_correct === false)
                                <p class="text-xs text-muted mt-2">{{ __('راجع الدرس المرتبط بهذا السؤال قبل محاولتك القادمة.') }}</p>
                            @endif
                        @endunless
                    </x-ui.card>
                @endforeach
            </div>

            @if($open)
                <div class="sticky bottom-0 -mx-4 sm:-mx-6 mt-4 px-4 sm:px-6 py-3 bg-surface/95 backdrop-blur border-t border-line">
                    <x-ui.button type="submit" class="w-full sm:w-auto">{{ __('سلّم الاختبار') }}</x-ui.button>
                    <p class="text-2xs text-subtle mt-2">{{ __('لا يمكن التعديل بعد التسليم.') }}</p>
                </div>
            @else
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" :href="url('/learn/'.$course->slug.'/'.$item->getKey())">
                        {{ __('العودة إلى الكورس') }}
                    </x-ui.button>
                </div>
            @endif
        </form>
    </main>
</div>
</x-layouts.app>
