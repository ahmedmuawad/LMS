@props(['removable' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-md bg-surface-sunken text-muted border border-line']) }}>
    {{ $slot }}
    @if($removable)
        <button type="button" class="opacity-60 hover:opacity-100" aria-label="{{ __('إزالة') }}">✕</button>
    @endif
</span>
