@props(['item', 'assignment', 'course', 'enrollment'])
@php
    $submissions = App\Modules\Lms\Models\AssignmentSubmission::where('enrollment_id', $enrollment->getKey())
        ->where('assignment_id', $assignment->getKey())
        ->latest('attempt_no')->get();

    $latest = $submissions->first();
    $due = $assignment->dueFor($enrollment->started_at);
    $usedTries = $submissions->count();
    $canSubmit = $usedTries === 0
        || ($latest?->status === 'resubmit' && $usedTries <= (int) $assignment->max_resubmissions);
@endphp

<div class="grid gap-4">
    <x-ui.card :title="$assignment->title">
        @if($assignment->instructions)
            <div class="text-muted leading-relaxed whitespace-pre-line mb-4">{{ $assignment->instructions }}</div>
        @endif

        <x-ui.description-list :items="array_filter([
            __('الدرجة العظمى') => rtrim(rtrim(number_format((float) $assignment->max_marks, 2), '0'), '.'),
            __('درجة النجاح') => rtrim(rtrim(number_format((float) $assignment->passing_marks, 2), '0'), '.'),
            __('موعد التسليم') => $due?->translatedFormat('j F Y'),
            __('التسليم المتأخر') => $assignment->allow_late
                ? ((int) $assignment->late_penalty_percent > 0
                    ? __('مقبول بخصم :p%', ['p' => $assignment->late_penalty_percent])
                    : __('مقبول'))
                : __('غير مقبول'),
            __('حجم الملف') => $assignment->max_file_mb.' MB',
        ])" />
    </x-ui.card>

    @if($latest !== null)
        <x-ui.card :title="__('تسليمك')">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <x-ui.badge :tone="match($latest->status) {
                    'graded' => $latest->passed() ? 'success' : 'danger',
                    'resubmit' => 'warning',
                    default => 'info',
                }">
                    {{ __(App\Modules\Lms\Models\AssignmentSubmission::STATUSES[$latest->status] ?? $latest->status) }}
                </x-ui.badge>

                @if($latest->is_late)<x-ui.badge tone="warning">{{ __('متأخر') }}</x-ui.badge>@endif

                <span class="text-2xs text-subtle font-mono ms-auto">{{ $latest->submitted_at?->diffForHumans() }}</span>
            </div>

            @if($latest->marks !== null)
                <p class="font-mono text-2xl tabular mb-2">
                    {{ rtrim(rtrim(number_format((float) $latest->marks, 2), '0'), '.') }}<span class="text-subtle text-base"> / {{ rtrim(rtrim(number_format((float) $assignment->max_marks, 2), '0'), '.') }}</span>
                </p>
            @endif

            @if(filled($latest->feedback))
                <div class="rounded-md bg-surface-sunken p-3 text-sm leading-relaxed">
                    <p class="text-2xs text-subtle mb-1">{{ __('ملاحظات المدرّس') }}</p>
                    {{ $latest->feedback[app()->getLocale()] ?? $latest->feedback['ar'] ?? '' }}
                </div>
            @endif

            @if($latest->content)
                <details class="mt-3">
                    <summary class="text-xs text-muted cursor-pointer py-1">{{ __('ما سلّمته') }}</summary>
                    <div class="text-sm leading-relaxed whitespace-pre-line mt-2 text-muted">{{ $latest->content }}</div>
                </details>
            @endif
        </x-ui.card>
    @endif

    @if($canSubmit)
        <x-ui.card :title="$usedTries === 0 ? __('سلّم واجبك') : __('أعد التسليم')">
            <form method="POST" enctype="multipart/form-data"
                  action="{{ url('/learn/'.$course->slug.'/'.$item->getKey().'/assignment') }}">
                @csrf

                <x-ui.field :label="__('إجابتك')" for="content" :error="$errors->first('content')">
                    <x-ui.textarea name="content" id="content" rows="8"
                                   :placeholder="__('اكتب إجابتك هنا، أو ارفعها ملفاً بالأسفل.')">{{ old('content') }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.field :label="__('مرفقات')" for="files" :error="$errors->first('files.0')"
                            :hint="filled($assignment->allowed_extensions)
                                ? __('المسموح: :ext', ['ext' => implode('، ', $assignment->allowed_extensions)])
                                : null">
                    <input type="file" name="files[]" id="files" multiple
                           class="w-full text-sm file:me-3 file:px-4 file:py-2 file:rounded-md file:border-0
                                  file:bg-primary-subtle file:text-primary file:font-semibold file:cursor-pointer
                                  bg-surface border border-line-strong rounded-md p-2">
                </x-ui.field>

                @if($due?->isPast() && $assignment->allow_late)
                    <x-ui.alert tone="warning" class="mb-4">
                        {{ __('انقضى الموعد — سيُقبل تسليمك مع خصم التأخير.') }}
                    </x-ui.alert>
                @endif

                <x-ui.button type="submit">{{ __('سلّم') }}</x-ui.button>
            </form>
        </x-ui.card>
    @elseif($latest?->status === 'pending')
        <x-ui.alert tone="info" :title="__('سُلّم وينتظر التصحيح')">
            {{ __('ستصلك النتيجة والملاحظات فور مراجعة المدرّس.') }}
        </x-ui.alert>
    @endif
</div>
