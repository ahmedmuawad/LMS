@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => [], 'locales' => ['ar', 'en'], 'long' => false])
@php
    $current = (array) old($name, $value ?? []);
    $meta    = config('locales.supported', []);
@endphp
<fieldset class="mb-4">
    <legend class="text-sm font-semibold mb-1.5">
        {{ $label }}
        @if($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">{{ __('حقل مطلوب') }}</span>@endif
    </legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach($locales as $locale)
            @php $dir = $meta[$locale]['dir'] ?? 'ltr'; @endphp
            <label class="grid gap-1">
                <span class="text-2xs text-subtle font-mono">{{ $meta[$locale]['native'] ?? $locale }}</span>
                @if($long)
                    <x-ui.textarea :name="$name.'['.$locale.']'" rows="3" :dir="$dir"
                                   :invalid="$errors->has($name.'.'.$locale)">{{ $current[$locale] ?? '' }}</x-ui.textarea>
                @else
                    <x-ui.input :name="$name.'['.$locale.']'" :value="$current[$locale] ?? ''" :dir="$dir"
                                :invalid="$errors->has($name.'.'.$locale)" />
                @endif
            </label>
        @endforeach
    </div>
    @if($hint)<p class="text-xs text-subtle mt-1.5">{{ $hint }}</p>@endif
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</fieldset>
