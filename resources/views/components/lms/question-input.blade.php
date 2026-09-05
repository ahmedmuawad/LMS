@props([
    'type',
    'name',
    'options' => [],
    'value' => null,
    'disabled' => false,
    'correct' => [],       // يُمرَّر بعد التسليم لتوسيم الإجابة الصحيحة
])

{{--
    حقل الإجابة بحسب نوع السؤال.

    مصدرٌ واحد للامتحان وللمراجعة الذكية: نسختان تفترقان يوماً ما،
    فيظهر نوعُ سؤالٍ في الامتحان ولا يظهر في المراجعة — والطالب لا
    يعرف لماذا سؤالٌ عنده بلا حقل إجابة.
--}}

@php
    $given = is_array($value) ? array_map('strval', $value) : (string) ($value ?? '');
    $chosen = fn (string $key): bool => is_array($value)
        ? in_array($key, $given, true)
        : $given === $key;
    $isRight = fn (string $key): bool => in_array($key, array_map('strval', (array) $correct), true);
@endphp

@if($type === 'true_false')
    <div class="grid gap-2">
        @foreach(['1' => __('صح'), '0' => __('خطأ')] as $key => $label)
            <x-ui.checkbox type="radio" :name="$name" :value="$key" :disabled="$disabled"
                           :checked="$chosen((string) $key)">
                {{ $label }}
                @if($isRight((string) $key))
                    <span class="text-success text-xs font-semibold ms-1">✓ {{ __('الصحيحة') }}</span>
                @endif
            </x-ui.checkbox>
        @endforeach
    </div>

@elseif(in_array($type, ['single_choice', 'dropdown'], true))
    <div class="grid gap-2">
        @foreach($options as $key => $label)
            <x-ui.checkbox type="radio" :name="$name" :value="$key" :disabled="$disabled"
                           :checked="$chosen((string) $key)">
                <x-ui.math inline>{{ $label }}</x-ui.math>
                @if($isRight((string) $key))
                    <span class="text-success text-xs font-semibold ms-1">✓ {{ __('الصحيحة') }}</span>
                @endif
            </x-ui.checkbox>
        @endforeach
    </div>

@elseif($type === 'multiple_choice')
    <div class="grid gap-2">
        @foreach($options as $key => $label)
            <x-ui.checkbox :name="$name.'[]'" :value="$key" :disabled="$disabled"
                           :checked="$chosen((string) $key)">
                <x-ui.math inline>{{ $label }}</x-ui.math>
                @if($isRight((string) $key))
                    <span class="text-success text-xs font-semibold ms-1">✓ {{ __('الصحيحة') }}</span>
                @endif
            </x-ui.checkbox>
        @endforeach
    </div>

@elseif($type === 'essay')
    <x-ui.textarea :name="$name" rows="6" :disabled="$disabled"
                   :placeholder="__('اكتب إجابتك…')">{{ is_array($value) ? '' : $value }}</x-ui.textarea>

@else
    <x-ui.input :name="$name" :disabled="$disabled"
                value="{{ is_array($value) ? '' : $value }}"
                :placeholder="__('إجابتك…')" />
@endif
