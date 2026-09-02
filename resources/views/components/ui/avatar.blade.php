@props(['name' => '', 'src' => null, 'size' => 'md'])
@php
    $sizes = ['xs' => 'size-6 text-2xs', 'sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg'];
    $initials = collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)
        ->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp
<span {{ $attributes->merge(['class' => 'inline-grid place-items-center rounded-full overflow-hidden bg-primary-subtle text-primary font-semibold shrink-0 '.($sizes[$size] ?? $sizes['md'])]) }}>
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover" loading="lazy" width="40" height="40">
    @else
        <span aria-hidden="true">{{ $initials ?: '؟' }}</span>
        <span class="sr-only">{{ $name }}</span>
    @endif
</span>
