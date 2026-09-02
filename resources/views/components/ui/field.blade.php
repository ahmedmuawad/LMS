@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5 mb-4']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="text-sm font-semibold">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">{{ __('حقل مطلوب') }}</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if($error)
        <p class="text-xs text-danger flex items-center gap-1.5" @if($for) id="{{ $for }}-error" @endif>
            <span aria-hidden="true">✕</span>{{ $error }}
        </p>
    @elseif($hint)
        <p class="text-xs text-subtle" @if($for) id="{{ $for }}-hint" @endif>{{ $hint }}</p>
    @endif
</div>
