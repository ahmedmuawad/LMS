@props(['label' => null])
<label class="flex items-center gap-2 text-sm cursor-pointer">
    <input type="checkbox" {{ $attributes->merge(['class' => 'size-[17px] accent-[var(--color-primary)] rounded-sm']) }}>
    <span>{{ $label ?? $slot }}</span>
</label>
