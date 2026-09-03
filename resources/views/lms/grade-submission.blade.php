<x-layouts.admin :title="__('تصحيح واجب')" current="grading">
<div class="max-w-[820px]">

    <x-ui.page-header :title="$submission->assignment?->title"
                      :subtitle="$submission->enrollment?->user?->name.' · '.$submission->enrollment?->course?->title"
                      :back="url('/admin/grading')">
        <x-slot:actions>
            @if($submission->is_late)
                <x-ui.badge tone="warning">{{ __('تسليم متأخر') }}</x-ui.badge>
            @endif
            <x-ui.badge>{{ __('المحاولة :n', ['n' => $submission->attempt_no]) }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4">
        <x-ui.card :title="__('إجابة الطالب')">
            @if($submission->content)
                <div class="text-sm leading-relaxed whitespace-pre-line">{{ $submission->content }}</div>
            @else
                <p class="text-sm text-subtle">{{ __('لا نصّ — الإجابة في المرفقات.') }}</p>
            @endif

            @if(filled($submission->files))
                <ul class="grid gap-2 mt-4 pt-4 border-t border-line">
                    @foreach($submission->files as $file)
                        <li class="flex items-center gap-2 text-sm">
                            <span aria-hidden="true" class="text-subtle">◫</span>
                            <span class="min-w-0 truncate">{{ $file['name'] ?? __('مرفق') }}</span>
                            <span class="text-2xs text-subtle font-mono ms-auto shrink-0">
                                {{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('التصحيح')">
            <form method="POST" action="{{ url('/admin/grading/submissions/'.$submission->id) }}">
                @csrf @method('PUT')

                <x-ui.field :label="__('الدرجة')" for="marks" :error="$errors->first('marks')"
                            :hint="__('من :max. درجة النجاح :pass.', [
                                'max' => $submission->assignment?->max_marks,
                                'pass' => $submission->assignment?->passing_marks,
                            ])">
                    <x-ui.input type="number" step="0.5" min="0" max="{{ $submission->assignment?->max_marks }}"
                                name="marks" id="marks" class="font-mono"
                                value="{{ old('marks', $submission->marks) }}" />
                </x-ui.field>

                @if($submission->is_late && (int) $submission->assignment?->late_penalty_percent > 0)
                    <x-ui.alert tone="info" class="mb-4">
                        {{ __('سيُخصم :percent% من الدرجة تلقائياً لأن التسليم متأخر.', [
                            'percent' => $submission->assignment->late_penalty_percent,
                        ]) }}
                    </x-ui.alert>
                @endif

                <x-ui.field :label="__('ملاحظات للطالب')" for="feedback"
                            :hint="__('هنا يتعلّم فعلاً — اذكر ما نقص لا الدرجة وحدها.')">
                    <x-ui.textarea name="feedback" id="feedback" rows="5">{{ old('feedback') }}</x-ui.textarea>
                </x-ui.field>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" name="action" value="grade">{{ __('اعتماد الدرجة') }}</x-ui.button>
                    <x-ui.button type="submit" name="action" value="resubmit" variant="secondary">
                        {{ __('طلب إعادة التسليم') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
