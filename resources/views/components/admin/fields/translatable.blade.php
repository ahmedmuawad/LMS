@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => [], 'locales' => ['ar', 'en'], 'long' => false, 'math' => false])
@php
    $current = (array) old($name, $value ?? []);
    $meta    = config('locales.supported', []);
@endphp
<fieldset class="mb-4">
    <legend class="text-sm font-semibold mb-1.5 flex items-center gap-2 flex-wrap">
        <span>
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">{{ __('حقل مطلوب') }}</span>@endif
        </span>
        {{--
            اللوحة أعلى النموذج والحقل قد يكون في آخره — فزرٌّ بجانب الحقل
            نفسه يفتحها ويُرسيها أسفل الشاشة، فلا يبحث عنها أحد.
        --}}
        @if($math)
            <button type="button" data-math-open
                    class="inline-flex items-center gap-1 h-7 px-2 rounded-md border border-line-strong text-2xs font-medium
                           text-primary hover:bg-primary-subtle hover:border-primary transition-colors">
                <span aria-hidden="true">∑</span>{{ __('لوحة المعادلات') }}
            </button>
        @endif
    </legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach($locales as $locale)
            @php $dir = $meta[$locale]['dir'] ?? 'ltr'; @endphp
            <label class="grid gap-1">
                <span class="text-2xs text-subtle font-mono">{{ $meta[$locale]['native'] ?? $locale }}</span>
                @if($long)
                    <x-ui.textarea :name="$name.'['.$locale.']'" rows="3" :dir="$dir"
                                   :data-math-input="$math ? '' : null"
                                   :data-math-label="$math ? $label.' · '.($meta[$locale]['native'] ?? $locale) : null"
                                   :invalid="$errors->has($name.'.'.$locale)">{{ $current[$locale] ?? '' }}</x-ui.textarea>
                @else
                    <x-ui.input :name="$name.'['.$locale.']'" :value="$current[$locale] ?? ''" :dir="$dir"
                                :data-math-input="$math ? '' : null"
                                :data-math-label="$math ? $label.' · '.($meta[$locale]['native'] ?? $locale) : null"
                                :invalid="$errors->has($name.'.'.$locale)" />
                @endif
            </label>
        @endforeach
    </div>
    @if($hint)<p class="text-xs text-subtle mt-1.5">{{ $hint }}</p>@endif
    {{-- الحقل الفارغ لا يوحي بأنه يقبل معادلات: نقولها --}}
    @if($math)
        <p class="text-2xs text-subtle mt-1 flex items-center gap-1">
            <span aria-hidden="true">∑</span>
            {{ __('نصّ ومعادلات معاً: اكتب كالمعتاد، واضغط رمزاً من لوحة المعادلات لتُدرَج معادلة مرسومة عند المؤشّر.') }}
        </p>
    @endif
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</fieldset>
