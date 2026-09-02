@props([
    'variant' => 'primary',   // primary secondary subtle accent ghost danger link
    'size'    => 'md',        // sm md lg
    'as'      => 'button',
    'href'    => null,
    'icon'    => null,
    'loading' => false,
])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-md border
             transition-[background-color,border-color,transform] duration-150 select-none
             disabled:opacity-45 disabled:pointer-events-none active:translate-y-px';
    $variants = [
        'primary'   => 'bg-primary text-primary-on border-transparent hover:bg-primary-hover',
        'secondary' => 'bg-surface text-content border-line-strong hover:bg-surface-sunken',
        'subtle'    => 'bg-primary-subtle text-primary border-transparent hover:brightness-95',
        'accent'    => 'bg-accent text-accent-on border-transparent hover:brightness-95',
        'ghost'     => 'bg-transparent text-muted border-transparent hover:bg-surface-sunken hover:text-content',
        'danger'    => 'bg-danger text-white border-transparent hover:brightness-110',
        'link'      => 'bg-transparent text-primary border-transparent hover:underline px-0',
    ];
    $sizes = [
        'sm' => 'text-xs px-3 py-1.5',
        'md' => 'text-sm px-[18px] py-2.5',
        'lg' => 'text-base px-6 py-3',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
    $tag = $href ? 'a' : $as;
@endphp
<{{ $tag }}
    {{ $attributes->merge(['class' => $classes] + ($href ? ['href' => $href] : ['type' => 'button'])) }}
    @if($loading) aria-busy="true" disabled @endif
>
    @if($loading)
        <span class="inline-block size-4 rounded-full border-2 border-current border-t-transparent animate-spin" aria-hidden="true"></span>
    @elseif($icon)
        <span aria-hidden="true">{{ $icon }}</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
